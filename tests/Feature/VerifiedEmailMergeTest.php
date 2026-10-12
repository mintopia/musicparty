<?php

use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\SocialProviders\DiscordProvider;
use App\Services\SocialProviders\LaravelPassportProvider;
use App\Services\SocialProviders\SpotifyProvider;
use App\Services\SocialProviders\SpotifySearchProvider;
use App\Services\SocialProviders\SteamProvider;
use App\Services\SocialProviders\TwitchProvider;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\User as SocialiteUser;

function providerFor(string $class, string $code): SocialProvider
{
    return SocialProvider::factory()->create(['code' => $code, 'provider_class' => $class]);
}

function fakeRemoteUser(string $id, ?string $email, array $raw = []): void
{
    $remote = (new SocialiteUser)->setRaw($raw)->map([
        'id' => $id,
        'nickname' => 'nick'.$id,
        'name' => 'name'.$id,
        'email' => $email,
        'avatar' => null,
    ]);
    $remote->token = 'token';
    $remote->refreshToken = 'refresh';

    $driver = new class($remote)
    {
        public function __construct(private SocialiteUser $remote) {}

        public function user(): SocialiteUser
        {
            return $this->remote;
        }

        public function scopes(): static
        {
            return $this;
        }

        public function setConfig(): static
        {
            return $this;
        }

        public function with(): static
        {
            return $this;
        }
    };

    $manager = new class($driver) extends SocialiteManager
    {
        public function __construct(private object $driverDouble) {}

        public function buildProvider($provider, $config)
        {
            return $this->driverDouble;
        }
    };

    app()->instance(Factory::class, $manager);
    Socialite::clearResolvedInstances();
}

it('merges a verified discord login into the user with a verified matching account', function () {
    $existing = LinkedAccount::factory()->verified()->create(['email' => 'a@example.com']);
    $discord = providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);

    $user = $discord->user();

    expect($user->id)->toBe($existing->user_id)
        ->and(User::count())->toBe(1)
        ->and($discord->accounts()->first()->email_verified_at)->not->toBeNull();
});

it('does not merge when the incoming discord email is unverified', function (array $raw) {
    $existing = LinkedAccount::factory()->verified()->create(['email' => 'a@example.com']);
    $discord = providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'a@example.com', $raw);

    $user = $discord->user();

    expect($user->id)->not->toBe($existing->user_id)
        ->and(User::count())->toBe(2)
        ->and($discord->accounts()->first()->email_verified_at)->toBeNull();
})->with([
    'verified false' => [['verified' => false]],
    'verified missing' => [[]],
]);

it('does not merge into an unverified existing account', function () {
    $existing = LinkedAccount::factory()->create(['email' => 'a@example.com']);
    $discord = providerFor(DiscordProvider::class, 'discord');
    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);

    expect($discord->user()->id)->not->toBe($existing->user_id)
        ->and(User::count())->toBe(2);
});

it('never auto-merges for providers that cannot assert verification', function (string $class, string $code) {
    $existing = LinkedAccount::factory()->verified()->create(['email' => 'a@example.com']);
    $provider = providerFor($class, $code);
    fakeRemoteUser('x1', 'a@example.com', ['verified' => true]);

    expect($provider->user()->id)->not->toBe($existing->user_id)
        ->and($provider->accounts()->first()->email_verified_at)->toBeNull();
})->with([
    'spotify' => [SpotifyProvider::class, 'spotify'],
    'spotify search' => [SpotifySearchProvider::class, 'spotifysearch'],
    'passport' => [LaravelPassportProvider::class, 'laravelpassport'],
    'steam' => [SteamProvider::class, 'steam'],
]);

it('updates email_verified_at on each login', function () {
    $discord = providerFor(DiscordProvider::class, 'discord');

    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);
    $discord->user();
    expect($discord->accounts()->first()->email_verified_at)->not->toBeNull();

    fakeRemoteUser('d1', 'a@example.com', ['verified' => false]);
    $discord->user();
    expect($discord->accounts()->first()->email_verified_at)->toBeNull();
});

it('refuses to link an account whose email belongs to another user', function (bool $verified) {
    $other = LinkedAccount::factory()->create(['email' => 'a@example.com', 'email_verified_at' => $verified ? now() : null]);
    $me = User::factory()->create();
    $spotify = providerFor(SpotifyProvider::class, 'spotify');
    fakeRemoteUser('s1', 'a@example.com');

    expect(fn () => (new SpotifyProvider($spotify))->user($me))
        ->toThrow(SocialProviderException::class, 'Account is already associated with another user');
    expect($other->user_id)->not->toBe($me->id);
})->with([true, false]);

it('links an unverified-provider account for a logged in user with a fresh email', function () {
    $me = User::factory()->create();
    $spotify = providerFor(SpotifyProvider::class, 'spotify');
    fakeRemoteUser('s1', 'new@example.com');

    (new SpotifyProvider($spotify))->user($me);

    expect($me->accounts()->count())->toBe(1);
});

it('ends in one user after a discord login then a twitch login with the same verified email', function () {
    $discord = providerFor(DiscordProvider::class, 'discord');
    $twitch = providerFor(TwitchProvider::class, 'twitch');

    fakeRemoteUser('d1', 'a@example.com', ['verified' => true]);
    $first = $discord->user();
    fakeRemoteUser('t1', 'a@example.com');
    $second = $twitch->user();

    expect($second->id)->toBe($first->id)
        ->and(User::count())->toBe(1)
        ->and(LinkedAccount::count())->toBe(2);
});

it('backfills existing twitch linked accounts as verified in the migration', function () {
    $twitch = providerFor(TwitchProvider::class, 'twitch');
    $other = providerFor(SpotifyProvider::class, 'spotify');
    $t = LinkedAccount::factory()->create(['social_provider_id' => $twitch->id]);
    $o = LinkedAccount::factory()->create(['social_provider_id' => $other->id]);

    $migration = require database_path('migrations/2026_10_12_120000_add_email_verified_at_to_linked_accounts.php');
    $migration->down();
    $migration->up();

    expect(DB::table('linked_accounts')->where('id', $t->id)->value('email_verified_at'))->not->toBeNull()
        ->and(DB::table('linked_accounts')->where('id', $o->id)->value('email_verified_at'))->toBeNull();
});
