<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class RequiredSecretsServiceProvider extends ServiceProvider
{
    private const array SKIPPED_COMMANDS = ['wayfinder:generate', 'package:discover', 'key:generate'];

    private const array PLACEHOLDER_VALUES = [
        'null',
        'secret',
        'password',
        'changeme',
        'change-me',
        'musicparty',
        'swordfish',
        'root',
    ];

    public function boot(): void
    {
        if (! $this->app->isProduction() || $this->app->runningConsoleCommand(self::SKIPPED_COMMANDS)) {
            return;
        }

        $problems = [];

        foreach ($this->requiredSecrets() as $variable => $value) {
            $problem = $this->problemWith($variable, $value);

            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        if ($problems !== []) {
            throw new RuntimeException('Refusing to boot: '.implode('; ', $problems).'. Secrets must be supplied through the environment at runtime.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredSecrets(): array
    {
        $secrets = [
            'APP_KEY' => config('app.key'),
            'REVERB_APP_ID' => config('broadcasting.connections.reverb.app_id'),
            'REVERB_APP_KEY' => config('broadcasting.connections.reverb.key'),
            'REVERB_APP_SECRET' => config('broadcasting.connections.reverb.secret'),
        ];

        $default = (string) config('database.default');

        if (in_array(config("database.connections.{$default}.driver"), ['mysql', 'mariadb'], true)) {
            $secrets['DB_PASSWORD'] = config("database.connections.{$default}.password");
        }

        return $secrets;
    }

    private function problemWith(string $variable, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return "{$variable} is missing or empty";
        }

        if (in_array(strtolower(trim($value)), self::PLACEHOLDER_VALUES, true)) {
            return "{$variable} is set to a default or placeholder value";
        }

        return null;
    }
}
