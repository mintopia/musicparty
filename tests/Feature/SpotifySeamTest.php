<?php

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Spotify\SpotifyGateway;
use App\Services\SpotifySearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Random\Engine\Mt19937;
use Random\Randomizer;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

function seamSpotifyUser(): User
{
    $provider = SocialProvider::factory()->create(['code' => 'spotify']);
    foreach (['client_id', 'client_secret'] as $code) {
        $setting = new ProviderSetting;
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
        $setting->provider()->associate($provider);
        $setting->save();
    }
    $user = User::factory()->create();
    LinkedAccount::factory()->create([
        'user_id' => $user->id,
        'social_provider_id' => $provider->id,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
    ]);

    return $user;
}

function seamParty(User $user, array $attributes = []): Party
{
    $party = new Party;
    $party->forceFill(array_merge(['name' => 'Test Party', 'user_id' => $user->id, 'active' => false], $attributes));
    $party->save();

    return $party;
}

function seamUpcoming(Party $party, string $spotifyId, int $score = 5): UpcomingSong
{
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($party);
    $upcoming->song()->associate(Song::fromSpotify(FakeSpotify::track($spotifyId)));
    $upcoming->score = $score;
    $upcoming->save();

    return $upcoming;
}

function seamQueueTracks(Party $party): void
{
    (new ReflectionMethod($party, 'addTracksToQueue'))->invoke($party);
}

it('blocks stray http requests', function () {
    Http::get('https://api.spotify.com/v1/me');
})->throws(RuntimeException::class);

it('resolves the user spotify client from the container', function () {
    $fake = FakeSpotify::install();

    expect(app(SpotifyGateway::class)->client(seamSpotifyUser()))->toBe($fake);
});

it('falls back to the container client in the search service', function () {
    $fake = FakeSpotify::install();
    $user = seamSpotifyUser();
    $party = seamParty($user);

    $service = new SpotifySearchService($party, $party->members()->firstOrNew());
    $api = app(SpotifyGateway::class)->searchClient($party->user);

    expect($api)->toBe($fake);
});

it('builds a real client when nothing is bound', function () {
    expect(app(SpotifyWebAPI::class, ['options' => [], 'session' => null, 'request' => null]))
        ->toBeInstanceOf(SpotifyWebAPI::class)
        ->not->toBeInstanceOf(FakeSpotify::class);
});

it('serves sdk-shaped fixtures that Song can consume', function () {
    $song = Song::fromSpotify(FakeSpotify::track('abc'));

    expect($song->spotify_id)->toBe('abc')->and($song->artists)->toHaveCount(1);
});

it('lets the fake be bound before a party is created', function () {
    $fake = FakeSpotify::install();
    $fake->respond('getMyCurrentPlaybackInfo', FakeSpotify::playback(FakeSpotify::track('now')));
    $user = seamSpotifyUser();

    $party = seamParty($user, ['active' => true, 'backup_playlist_id' => 'backup']);

    expect($party->fresh()->song->spotify_id)->toBe('now')
        ->and($fake->callsTo('getMyCurrentPlaybackInfo'))->not->toBeEmpty();
});

it('orders the fallback playlist exactly with a seeded randomizer', function () {
    $ids = ['a', 'b', 'c', 'd', 'e'];
    $expected = (new Randomizer(new Mt19937(42)))->shuffleArray($ids);
    app()->bind(Randomizer::class, fn () => new Randomizer(new Mt19937(42)));
    FakeSpotify::install()->withBackupPlaylist($ids);
    $user = seamSpotifyUser();

    $party = seamParty($user, ['active' => true, 'backup_playlist_id' => 'backup']);

    $added = $party->upcoming()->orderBy('id')->get()->map(fn (UpcomingSong $u) => $u->song->spotify_id)->all();
    expect($added)->toBe(array_slice($expected, 0, count($added)))->not->toBeEmpty();
});

it('selects weighted queue songs exactly with a seeded randomizer', function () {
    $fake = FakeSpotify::install();
    $fake->respond('getMyQueue', (object) ['currently_playing' => FakeSpotify::track('now'), 'queue' => [FakeSpotify::track('now')]]);
    $user = seamSpotifyUser();
    $party = seamParty($user, ['weighted' => true]);
    $songs = collect(['s1', 's2', 's3'])->map(fn ($id) => seamUpcoming($party, $id));
    app()->bind(Randomizer::class, fn () => new Randomizer(new Mt19937(7)));

    seamQueueTracks($party->refresh());
    $first = $fake->callsTo('queue');

    foreach ($songs as $s) {
        $s->queued_at = null;
        $s->save();
    }
    $fake->calls = [];
    app()->bind(Randomizer::class, fn () => new Randomizer(new Mt19937(7)));
    seamQueueTracks($party->refresh());

    expect($first)->not->toBeEmpty()->and($fake->callsTo('queue'))->toBe($first);
});

it('freezes time with travelTo, which is reset after each test', function () {
    $this->travelTo(Carbon::parse('2025-01-01 12:00:00'));

    expect(now()->toDateTimeString())->toBe('2025-01-01 12:00:00');
});
