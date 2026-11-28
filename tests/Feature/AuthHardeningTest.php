<?php

use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use App\Providers\TrustedRootUrlServiceProvider;
use App\Services\SocialProviders\DiscordProvider;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

function dedupeMigration(): object
{
    return require database_path('migrations/2026_11_28_120000_add_unique_index_to_linked_accounts.php');
}

function sessionConfigWith(?string $env, ?string $secure): array
{
    $names = ['APP_ENV' => $env, 'SESSION_SECURE_COOKIE' => $secure];
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

it('defaults the session cookie to secure in production only', function () {
    expect(sessionConfigWith('production', null)['secure'])->toBeTrue()
        ->and(sessionConfigWith('local', null)['secure'])->toBeFalse()
        ->and(sessionConfigWith('production', 'false')['secure'])->toBeFalse();
});

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

it('does not rate limit the theme stylesheet route by address', function () {
    foreach (range(1, 80) as $i) {
        expect($this->get('/theme/missing-'.$i.'.css')->status())->toBe(404);
    }
});

it('does not rate limit guest party routes by address', function () {
    $party = Party::factory()->create();

    foreach (range(1, 200) as $i) {
        $this->getJson(route('api.v1.parties.show', $party))->assertOk();
    }
});
