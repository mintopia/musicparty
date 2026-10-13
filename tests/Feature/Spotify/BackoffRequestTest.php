<?php

use App\Enums\SettingType;
use App\Jobs\PartyUpdate;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Spotify\BackoffRequest;
use App\Services\Spotify\SpotifyBackoffException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
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
    $party = Mockery::mock(Party::class)->makePartial();
    $party->code = 'ABCD';
    $party->poll = false;
    $party->shouldReceive('tryUpdateState')->andThrow(new SpotifyBackoffException(9));

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(9);
    $queueJob->shouldReceive('isReleased', 'isDeletedOrReleased')->andReturn(false);

    $job = new PartyUpdate($party);
    $job->setJob($queueJob);
    $job->handle();
});

it('shows a friendly error instead of a 500 when search hits a backoff', function () {
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
        ->assertSee('try again shortly');
});
