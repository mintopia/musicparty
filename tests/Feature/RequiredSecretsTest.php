<?php

use App\Providers\RequiredSecretsServiceProvider;
use Illuminate\Support\Facades\File;

/**
 * @param  array<string, mixed>  $overrides
 */
function bootRequiredSecrets(array $overrides = [], string $environment = 'production'): void
{
    config(array_merge([
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'database.connections.sqlite.driver' => 'mysql',
        'database.connections.sqlite.password' => 'a-real-db-password',
        'broadcasting.connections.reverb.app_id' => 'app-id-123',
        'broadcasting.connections.reverb.key' => 'reverb-key-123',
        'broadcasting.connections.reverb.secret' => 'reverb-secret-123',
    ], $overrides));

    app()['env'] = $environment;
    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        new RequiredSecretsServiceProvider(app())->boot();
    } finally {
        $_SERVER['argv'] = $argv;
        app()['env'] = 'testing';
    }
}

it('boots in production when every secret is supplied', function () {
    bootRequiredSecrets();

    expect(true)->toBeTrue();
});

it('names the variable when a secret is missing, empty or a default', function (string $configKey, string $variable, ?string $value) {
    expect(fn () => bootRequiredSecrets([$configKey => $value]))
        ->toThrow(RuntimeException::class, $variable);
})->with([
    'empty app key' => ['app.key', 'APP_KEY', ''],
    'missing reverb id' => ['broadcasting.connections.reverb.app_id', 'REVERB_APP_ID', null],
    'default reverb key' => ['broadcasting.connections.reverb.key', 'REVERB_APP_KEY', 'musicparty'],
    'default reverb secret' => ['broadcasting.connections.reverb.secret', 'REVERB_APP_SECRET', 'swordfish'],
    'default db password' => ['database.connections.sqlite.password', 'DB_PASSWORD', 'musicparty'],
    'null-string db password' => ['database.connections.sqlite.password', 'DB_PASSWORD', 'null'],
]);

it('does not enforce secrets outside production', function () {
    bootRequiredSecrets(['broadcasting.connections.reverb.secret' => ''], 'local');

    expect(true)->toBeTrue();
});

it('bakes no secret into the production image inputs', function () {
    $env = File::get(base_path('docker/production/production.env'));

    expect($env)->not->toMatch('/^(APP_KEY|DB_PASSWORD|REDIS_PASSWORD|REVERB_APP_(ID|KEY|SECRET))=/m');
    expect($env)->not->toContain('swordfish');
});

it('requires every secret in the production compose file', function () {
    $compose = File::get(base_path('docker-compose.production.example.yml'));

    foreach (['APP_KEY', 'DB_PASSWORD', 'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'] as $variable) {
        expect($compose)->toContain('${'.$variable.':?set '.$variable.'}');
    }
});
