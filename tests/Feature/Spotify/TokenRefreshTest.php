<?php

use App\Enums\SettingType;
use App\Jobs\PartyUpdate;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\SocialProviders\SpotifyProvider;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;

use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Two\User as SocialiteUser;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

function scriptedSession(array $script, ?object &$request = null): Session
{
    $request = new class($script) extends Request
    {
        public int $calls = 0;

        public function __construct(public array $script)
        {
            parent::__construct();
        }

        public function account(string $method, string $uri, $parameters = [], array $headers = []): array
        {
            $this->calls++;
            $outcome = array_shift($this->script);
            if ($outcome instanceof Throwable) {
                throw $outcome;
            }

            return ['body' => $outcome, 'headers' => [], 'status' => 200, 'url' => $uri];
        }
    };

    return new Session('id', 'secret', '', $request);
}

function expiringSpotifyAccount(string $code = 'spotify'): LinkedAccount
{
    $provider = SocialProvider::where('code', $code)->first() ?? SocialProvider::factory()->create(['code' => $code]);

    return LinkedAccount::factory()->create([
        'user_id' => User::factory()->create()->id,
        'social_provider_id' => $provider->id,
        'access_token' => 'old',
        'refresh_token' => 'refresh-1',
        'access_token_expires_at' => now()->addMinute(),
    ]);
}

it('persists a rotated refresh token', function () {
    $account = expiringSpotifyAccount();
    $session = scriptedSession([(object) ['access_token' => 'new', 'expires_in' => 3600, 'refresh_token' => 'refresh-2']]);

    $account->refreshSpotifyToken($session);

    $fresh = $account->fresh();
    expect($fresh->access_token)->toBe('new')
        ->and($fresh->refresh_token)->toBe('refresh-2')
        ->and($fresh->access_token_expires_at->isFuture())->toBeTrue();
});

it('re-reads the token inside the lock and skips a refresh another process already did', function () {
    $account = expiringSpotifyAccount();
    $stale = LinkedAccount::find($account->id);
    LinkedAccount::whereKey($account->id)->first()->forceFill([
        'access_token' => 'already-fresh',
        'access_token_expires_at' => now()->addHour(),
    ])->save();
    $session = scriptedSession([], $request);

    $stale->refreshSpotifyToken($session);

    expect($request->calls)->toBe(0)
        ->and($session->getAccessToken())->toBe('already-fresh');
});

it('holds the per-account lock while calling Spotify', function () {
    $account = expiringSpotifyAccount();
    $heldDuringCall = null;
    $request = new class extends Request
    {
        public ?Closure $probe = null;

        public function account(string $method, string $uri, $parameters = [], array $headers = []): array
        {
            ($this->probe)();

            return ['body' => (object) ['access_token' => 'new', 'expires_in' => 3600], 'headers' => [], 'status' => 200, 'url' => $uri];
        }
    };
    $request->probe = function () use ($account, &$heldDuringCall): void {
        $other = Cache::lock("linked-account-refresh:{$account->id}", 5);
        $heldDuringCall = ! $other->get();
    };

    $account->refreshSpotifyToken(new Session('id', 'secret', '', $request));

    expect($heldDuringCall)->toBeTrue()
        ->and(Cache::lock("linked-account-refresh:{$account->id}", 5)->get())->toBeTrue();
});

it('treats a refresh without an access token as a failure', function () {
    $account = expiringSpotifyAccount();
    $session = scriptedSession([(object) []]);

    expect(fn () => $account->refreshSpotifyToken($session))->toThrow(SpotifyWebAPIException::class);
    expect($account->fresh()->needsRelink())->toBeFalse();
});

it('marks the account for re-link on a revoked refresh without re-throwing the HTTP error', function (int $status) {
    $account = expiringSpotifyAccount();
    $session = scriptedSession([new SpotifyWebAPIException('invalid_grant', $status)]);

    expect(fn () => $account->refreshSpotifyToken($session))->toThrow(SpotifyRelinkRequiredException::class);

    expect($account->fresh()->needsRelink())->toBeTrue();
})->with([400, 401]);

it('does not call Spotify again once the account needs re-link', function () {
    $account = expiringSpotifyAccount();
    $account->forceFill(['needs_relink_at' => now()])->save();
    $session = scriptedSession([], $request);

    expect(fn () => $account->refreshSpotifyToken($session))->toThrow(SpotifyRelinkRequiredException::class);
    expect($request->calls)->toBe(0);
});

it('skips flagged accounts in the minute refresh command', function () {
    $account = expiringSpotifyAccount();
    $account->forceFill(['needs_relink_at' => now()])->save();

    $this->artisan('party:refreshaccesstokens')->doesntExpectOutputToContain('Refreshing')->assertSuccessful();
});

it('stops the poll chain and the fallback for a party that needs re-link', function () {
    Queue::fake();
    $account = expiringSpotifyAccount();
    $account->forceFill(['needs_relink_at' => now()])->save();
    $party = Party::factory()->create(['user_id' => $account->user_id]);
    $party->forceFill(['active' => true, 'poll' => true, 'last_updated_at' => null])->saveQuietly();

    (new PartyUpdate($party->fresh()))->handle();
    $this->artisan('party:fallback')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('shows the re-link banner to managers but not guests, and re-linking clears it', function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    $account = expiringSpotifyAccount();
    $account->forceFill(['needs_relink_at' => now()])->save();
    $account->user->forceFill(['first_login' => false])->save();
    $party = Party::factory()->create(['user_id' => $account->user_id]);

    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('parties.show', $party->code))
        ->assertOk()->assertDontSee('spotify-relink-banner');
    $this->actingAs($account->user)->get(route('parties.show', $party->code))
        ->assertOk()->assertSee('Spotify needs re-linking');

    $remote = new SocialiteUser;
    $remote->token = 'tok';
    $remote->refreshToken = 'ref';
    $provider = app(SpotifyProvider::class);
    (new ReflectionMethod($provider, 'updateAccount'))->invoke($provider, $account, $remote);
    $account->save();

    $this->actingAs($account->user)->get(route('parties.show', $party->code))
        ->assertOk()->assertDontSee('spotify-relink-banner');
});

it('falls back to the control account when the search account needs re-link', function () {
    $fake = FakeSpotify::install();
    $control = expiringSpotifyAccount();
    foreach (['client_id', 'client_secret'] as $code) {
        $setting = new ProviderSetting;
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
        $setting->provider()->associate($control->provider);
        $setting->save();
    }
    $control->forceFill(['access_token_expires_at' => now()->addHour()])->save();
    $search = LinkedAccount::factory()->create([
        'user_id' => $control->user_id,
        'social_provider_id' => SocialProvider::factory()->create(['code' => 'spotifysearch'])->id,
        'needs_relink_at' => now(),
    ]);
    $party = Party::factory()->create(['user_id' => $control->user_id]);

    $api = app(SpotifyGateway::class)->searchClient($party->user);

    expect($api)->toBe($fake)->and($search->fresh()->needsRelink())->toBeTrue();
});
