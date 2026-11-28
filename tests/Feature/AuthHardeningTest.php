<?php

use App\Http\Middleware\TrustProxies;
use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Models\User;
use App\Providers\TrustedRootUrlServiceProvider;
use App\Services\SocialProviders\DiscordProvider;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

function dedupeMigration(): object
{
    return require database_path('migrations/2026_11_28_120000_add_unique_index_to_linked_accounts.php');
}

function sessionConfigWith(?string $appUrl, ?string $secure): array
{
    $names = ['APP_URL' => $appUrl, 'SESSION_SECURE_COOKIE' => $secure];
    $previous = array_map(getenv(...), array_keys($names));
    $apply = function (string $name, string|false|null $value): void {
        if ($value === null || $value === false) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }
        putenv("$name=$value");
        $_ENV[$name] = $_SERVER[$name] = $value;
    };

    foreach ($names as $name => $value) {
        $apply($name, $value);
    }

    try {
        return require config_path('session.php');
    } finally {
        foreach (array_keys($names) as $index => $name) {
            $apply($name, $previous[$index]);
        }
    }
}

it('builds urls from app.url in production even with a forged host header', function () {
    config(['app.url' => 'https://music.example.com']);
    app()['env'] = 'production';
    new TrustedRootUrlServiceProvider(app())->boot();

    $this->get('http://evil.example/login', ['Host' => 'evil.example']);

    expect(route('login.return', 'steam'))->toStartWith('https://music.example.com/')
        ->and(URL::to('/x'))->toBe('https://music.example.com/x');
});

it('defaults the session cookie secure flag from the app url scheme', function () {
    expect(sessionConfigWith('https://music.example.com', null)['secure'])->toBeTrue()
        ->and(sessionConfigWith('http://music.example.com', null)['secure'])->toBeFalse()
        ->and(sessionConfigWith('https://music.example.com', 'false')['secure'])->toBeFalse();
});

it('trusts proxies from the configured TRUSTED_PROXIES list', function (string $configured, string|array $expected) {
    config(['app.trusted_proxies' => $configured]);
    $middleware = new TrustProxies;

    expect((fn () => $this->proxies())->call($middleware))->toBe($expected);
})->with([
    'wildcard' => ['*', '*'],
    'list' => ['10.0.0.0/8, 172.16.0.1', ['10.0.0.0/8', '172.16.0.1']],
    'blank falls back to wildcard' => ['', '*'],
]);

it('fails fast in production on an empty or localhost app url', function (string $url) {
    config(['app.url' => $url]);
    app()['env'] = 'production';

    expect(fn () => new TrustedRootUrlServiceProvider(app())->boot())->toThrow(RuntimeException::class, 'APP_URL');
})->with(['', 'http://localhost', 'https://127.0.0.1:8080']);

it('warns at boot when the cookie secure flag mismatches the app url scheme', function (string $url, bool $secure, bool $warns) {
    config(['app.url' => $url, 'session.secure' => $secure]);
    app()['env'] = 'production';
    Log::spy();

    new TrustedRootUrlServiceProvider(app())->boot();

    $warns ? Log::shouldHaveReceived('warning')->once() : Log::shouldNotHaveReceived('warning');
})->with([
    'http with secure cookie' => ['http://music.example.com', true, true],
    'https without secure cookie' => ['https://music.example.com', false, true],
    'http matching' => ['http://music.example.com', false, false],
    'https matching' => ['https://music.example.com', true, false],
]);

it('creates a single linked account when two first logins race', function () {
    $provider = providerFor(DiscordProvider::class, 'discord');
    $winner = User::factory()->create();
    fakeRemoteUser('race-1', null);
    $raced = false;
    LinkedAccount::creating(function () use (&$raced) {
        if (! $raced) {
            $raced = true;

            throw new UniqueConstraintViolationException('sqlite', 'insert into linked_accounts', [], new Exception('duplicate'));
        }
    });
    Event::listen(TransactionRolledBack::class, function () use ($provider, $winner) {
        LinkedAccount::query()->insertOrIgnore([
            'user_id' => $winner->id,
            'social_provider_id' => $provider->id,
            'external_id' => 'race-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $user = $provider->user();

    expect($user->id)->toBe($winner->id)
        ->and(LinkedAccount::count())->toBe(1)
        ->and(User::count())->toBe(1);
});

it('dedupes existing linked accounts then enforces uniqueness per provider and external id', function () {
    $migration = dedupeMigration();
    $migration->down();
    $provider = SocialProvider::factory()->create();
    $other = SocialProvider::factory()->create();
    $first = LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'x']);
    LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'x']);
    $otherProvider = LinkedAccount::factory()->create(['social_provider_id' => $other->id, 'external_id' => 'x']);

    $migration->up();

    expect(LinkedAccount::pluck('id')->sort()->values()->all())->toBe([$first->id, $otherProvider->id])
        ->and(fn () => LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'x']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('returns 404 when linking a disabled provider', function () {
    $provider = SocialProvider::factory()->create(['code' => 'off', 'enabled' => false]);
    $this->actingAs(User::factory()->create());

    $this->get(route('linkedaccounts.create', $provider))->assertNotFound();
    $this->get(route('linkedaccounts.store', $provider))->assertNotFound();
});

it('throttles the theme stylesheet route', function () {
    Theme::factory()->create(['code' => 'neon']);

    foreach (range(1, 60) as $i) {
        expect($this->get('/theme/missing-'.$i.'.css')->status())->toBe(404);
    }

    $this->get('/theme/missing-final.css')->assertTooManyRequests();
});
