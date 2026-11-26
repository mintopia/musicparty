<?php

use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Jobs\PartyUpdate;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PlaybackSync;
use App\Services\Spotify\BackoffRequest;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\SpotifyWebAPIException;
use Tests\Support\FakeSpotify;

function scriptedRequest(string $application, array $script): object
{
    return new class($application, $script) extends BackoffRequest
    {
        public int $httpCalls = 0;

        public array $headers = [];

        public function __construct(string $application, protected array $script)
        {
            parent::__construct($application);
        }

        protected function transport(string $method, string $url, string|array|object $parameters, array $headers): array
        {
            $this->httpCalls++;
            $outcome = array_shift($this->script);
            if ($outcome instanceof Throwable) {
                throw $outcome;
            }

            return ['body' => $outcome, 'headers' => [], 'status' => 200, 'url' => $url];
        }

        public function getLastResponse(): array
        {
            return ['headers' => $this->headers];
        }
    };
}

it('records a backoff on 429 and makes no HTTP request while it is active', function () {
    $request = scriptedRequest('spotify', [new SpotifyWebAPIException('rate limited', 429), 'ok']);
    $request->headers = ['retry-after' => '7'];

    expect(fn () => $request->send('GET', 'https://api.spotify.com/v1/me'))->toThrow(SpotifyWebAPIException::class);
    expect(BackoffRequest::backoffRemaining('spotify'))->toBeBetween(6, 7);

    expect(fn () => $request->send('GET', 'https://api.spotify.com/v1/me'))->toThrow(SpotifyBackoffException::class);
    expect($request->httpCalls)->toBe(1);
});

it('keys backoff per client application', function () {
    $search = scriptedRequest('spotifysearch', [new SpotifyWebAPIException('rate limited', 429)]);
    $search->headers = ['retry-after' => '30'];
    expect(fn () => $search->send('GET', 'x'))->toThrow(SpotifyWebAPIException::class);

    $control = scriptedRequest('spotify', ['ok']);
    $control->send('GET', 'x');

    expect($control->httpCalls)->toBe(1)
        ->and(BackoffRequest::backoffRemaining('spotify'))->toBe(0)
        ->and(BackoffRequest::backoffRemaining('spotifysearch'))->toBeGreaterThan(0);
});

it('resumes calling Spotify once the backoff expires', function () {
    Cache::put('spotify:backoff:spotify', time() - 1, 60);
    $request = scriptedRequest('spotify', ['ok']);

    $request->send('GET', 'x');

    expect($request->httpCalls)->toBe(1);
});

it('leaves non-429 exceptions untouched and sets no backoff', function () {
    $request = scriptedRequest('spotify', [new SpotifyWebAPIException('nope', 404)]);

    expect(fn () => $request->send('GET', 'x'))->toThrow(SpotifyWebAPIException::class, 'nope');
    expect(BackoffRequest::backoffRemaining('spotify'))->toBe(0);
});

it('releases a party update job until the backoff expires', function () {
    $party = Party::factory()->create(['poll' => false]);
    $sync = Mockery::mock(PlaybackSync::class);
    $sync->shouldReceive('sync')->andThrow(new SpotifyBackoffException(9));
    app()->instance(PlaybackSync::class, $sync);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(9);
    $queueJob->shouldReceive('isReleased', 'isDeletedOrReleased')->andReturn(false);

    $job = new PartyUpdate($party);
    $job->setJob($queueJob);
    $job->handle();
});

it('shows a friendly error instead of a 500 when search hits a backoff', function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $fake = new class extends FakeSpotify
    {
        public function search(string $query, array|string $type, object|array $options = []): object|array
        {
            throw new SpotifyBackoffException(5);
        }
    };
    app()->bind(SpotifyWebAPI\SpotifyWebAPI::class, fn () => $fake);

    $provider = SocialProvider::factory()->create(['code' => 'spotify']);
    foreach (['client_id', 'client_secret'] as $code) {
        $setting = new ProviderSetting;
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
        $setting->provider()->associate($provider);
        $setting->save();
    }
    $owner = User::factory()->create();
    LinkedAccount::factory()->create([
        'user_id' => $owner->id,
        'social_provider_id' => $provider->id,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
    ]);
    $party = new Party;
    $party->forceFill(['code' => 'ABCD', 'name' => 'Test Party', 'user_id' => $owner->id, 'active' => false, 'allow_requests' => true]);
    Party::withoutEvents(fn () => $party->save());
    $role = PartyMemberRole::query()->where('code', 'member')->first() ?? PartyMemberRole::query()->forceCreate(['code' => 'member', 'name' => 'Member']);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = $owner->id;
    $member->role_id = $role->id;
    $member->save();

    $this->withoutVite()->actingAs($owner)
        ->get(route('parties.search', ['party' => $party->code, 'query' => 'abba']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('results.error.code', 'busy')
            ->where('results.error.retryable', true)));
});

it('maps Spotify search failures to friendly deferred errors', function (Throwable $failure, string $code, bool $backoff) {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $fake = new class($failure) extends FakeSpotify
    {
        public function __construct(private Throwable $failure) {}

        public function search(string $query, array|string $type, object|array $options = []): object|array
        {
            throw $this->failure;
        }
    };
    app()->bind(SpotifyWebAPI\SpotifyWebAPI::class, fn () => $fake);
    $owner = User::factory()->create();
    $provider = SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->create(['user_id' => $owner->id, 'social_provider_id' => $provider->id, 'access_token' => 'token', 'refresh_token' => 'refresh', 'access_token_expires_at' => now()->addHour()]);
    $party = new Party;
    $party->forceFill(['code' => 'WXYZ', 'name' => 'Test Party', 'user_id' => $owner->id, 'active' => false, 'allow_requests' => true]);
    Party::withoutEvents(fn () => $party->save());
    $role = PartyMemberRole::query()->where('code', 'member')->first() ?? PartyMemberRole::query()->forceCreate(['code' => 'member', 'name' => 'Member']);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = $owner->id;
    $member->role_id = $role->id;
    $member->save();

    $this->withoutVite()->actingAs($owner)
        ->get(route('parties.search', ['party' => $party->code, 'query' => 'abba']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('results.error.code', $code)
            ->where('results.error.retryable', true)));
})->with([
    'curl timeout' => [fn () => new SpotifyWebAPIException('cURL transport error: 28 Operation timed out after 8001 milliseconds'), 'timeout', false],
    'connection refused' => [fn () => new SpotifyWebAPIException('cURL transport error: 7 Failed to connect'), 'unavailable', false],
    'rate limited' => [fn () => new SpotifyWebAPIException('rate limited', 429), 'busy', true],
    'backoff active' => [fn () => new SpotifyBackoffException(5), 'busy', true],
]);

it('retries a GET once after a connection failure but not after a timeout or for other methods', function () {
    $connect = new SpotifyWebAPIException('cURL transport error: 7 Failed to connect');
    $request = scriptedRequest('spotify', [$connect, 'ok']);
    expect($request->send('GET', 'https://api.spotify.com/v1/search')['body'])->toBe('ok')->and($request->httpCalls)->toBe(2);

    $request = scriptedRequest('spotify', [new SpotifyWebAPIException('cURL transport error: 28 timed out'), 'ok']);
    expect(fn () => $request->send('GET', 'https://api.spotify.com/v1/search'))->toThrow(SpotifyWebAPIException::class);
    expect($request->httpCalls)->toBe(1);

    $request = scriptedRequest('spotify', [$connect, 'ok']);
    expect(fn () => $request->send('POST', 'https://api.spotify.com/v1/x'))->toThrow(SpotifyWebAPIException::class);
    expect($request->httpCalls)->toBe(1);
});

it('applies the configured HTTP timeouts to Spotify requests', function () {
    config(['musicparty.spotify_timeout_seconds' => 3, 'musicparty.spotify_connect_timeout_seconds' => 2]);
    $options = new ReflectionProperty(Request::class, 'options')->getValue(BackoffRequest::forApplication('spotify'));

    expect($options['curl_options'])->toBe([CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2]);
});
