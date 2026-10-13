<?php

use App\Events\Party\UpdatedEvent as PartyUpdatedEvent;
use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Events\WebPlayerTokenRefreshed;
use App\Jobs\ApplyPushedPlaybackState;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Playback\PushedStateRouter;
use App\Services\Playback\WebPlayerStateMapper;
use App\Services\PlaybackSync;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeSpotify;

function webPlayerFrame(array $overrides = []): array
{
    return array_merge([
        'uri' => 'fake:track:A',
        'paused' => false,
        'position' => 4200,
        'duration' => 200000,
        'timestamp' => 1_760_000_000_000,
        'shuffle' => true,
        'repeat' => 'context',
        'deviceId' => 'dev-1',
    ], $overrides);
}

function webPlayerParty(): array
{
    FakeSpotify::install();
    $party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'poll' => true, 'backup_playlist_id' => 'BACKUP']);
    Cache::flush();
    $fake = app(FakeMusicProvider::class);
    foreach (['A', 'B'] as $id) {
        $fake->withTrack(new Track('fake', $id, "Name {$id}", 200000, false, true, new AlbumRef("album-{$id}", 'Album', null), [new ArtistRef('artist1', 'Artist')]));
    }

    return [$party, $fake];
}

it('maps an SDK state to a WebPlayer PlaybackState sampled at the SDK timestamp', function () {
    [$party] = webPlayerParty();

    $state = app(WebPlayerStateMapper::class)->map($party, 'client-state', webPlayerFrame());

    expect($state->source)->toBe(PlaybackSource::WebPlayer)
        ->and($state->isPlaying)->toBeTrue()
        ->and($state->trackId)->toBe('A')
        ->and($state->progressMs)->toBe(4200)
        ->and($state->durationMs)->toBe(200000)
        ->and($state->sampledAt->getTimestampMs())->toBe(1_760_000_000_000)
        ->and($state->shuffle)->toBeTrue()
        ->and($state->repeat)->toBe('context')
        ->and($state->deviceId)->toBe('dev-1');
});

it('drops frames with another event name or without a uri or timestamp', function (string $event, array $data) {
    [$party] = webPlayerParty();

    expect(app(WebPlayerStateMapper::class)->map($party, $event, $data))->toBeNull();
})->with([
    'wrong event' => ['client-other', webPlayerFrame()],
    'no uri' => ['client-state', ['timestamp' => 1, 'paused' => false]],
    'no timestamp' => ['client-state', ['uri' => 'fake:track:A']],
]);

it('routes the private webplayer channel to the mapper', function () {
    $route = app(PushedStateRouter::class)->route('private-webplayer.ABCD');

    expect($route[0])->toBeInstanceOf(WebPlayerStateMapper::class)->and($route[1])->toBe('ABCD');
});

it('applies recorded SDK states through the mapper with zero currentPlayback calls', function () {
    [$party, $fake] = webPlayerParty();
    $channel = "private-webplayer.{$party->code}";

    foreach ([['A', 1_760_000_000_000], ['B', 1_760_000_005_000]] as [$id, $timestamp]) {
        (new ApplyPushedPlaybackState($channel, 'client-state', json_encode(webPlayerFrame(['uri' => "fake:track:{$id}", 'timestamp' => $timestamp]))))
            ->handle(app(PushedStateRouter::class), app(PlaybackSync::class));
    }

    $fresh = $party->fresh();
    expect($fresh->playback_source)->toBe(PlaybackSource::WebPlayer)
        ->and($fresh->song?->provider_track_id)->toBe('B')
        ->and($fake->callsTo('currentPlayback'))->toBe([]);
});

it('broadcasts a refreshed token on the private webplayer channel of each Party the user hosts', function () {
    Event::fake([WebPlayerTokenRefreshed::class]);
    $host = User::factory()->create();
    $mine = Party::factory()->create(['user_id' => $host->id]);
    Party::factory()->create();

    event(new SpotifyAccessTokenUpdatedEvent($host, 'fresh-token'));

    Event::assertDispatchedTimes(WebPlayerTokenRefreshed::class, 1);
    Event::assertDispatched(WebPlayerTokenRefreshed::class, function (WebPlayerTokenRefreshed $event) use ($mine) {
        $channel = $event->broadcastOn();

        return $channel instanceof PrivateChannel
            && $channel->name === "private-webplayer.{$mine->code}"
            && $event->broadcastWith() === ['accessToken' => 'fresh-token'];
    });
});

it('records the Played Track, tops up the Queue and broadcasts once from a Web Player state', function () {
    [$party, $fake] = webPlayerParty();
    $queued = Song::factory()->create(['provider_id' => 'fake', 'provider_track_id' => 'B']);
    $upcoming = UpcomingSong::factory()->for($party)->create(['song_id' => $queued->id, 'queued_at' => null]);
    Event::fake([PartyUpdatedEvent::class]);

    (new ApplyPushedPlaybackState("private-webplayer.{$party->code}", 'client-state', json_encode(webPlayerFrame())))
        ->handle(app(PushedStateRouter::class), app(PlaybackSync::class));

    expect(PlayedSong::query()->sole()->song->provider_track_id)->toBe('A')
        ->and($upcoming->fresh()->queued_at)->not->toBeNull()
        ->and($fake->callsTo('sendToPlayer'))->toHaveCount(1);
    Event::assertDispatchedTimes(PartyUpdatedEvent::class, 1);
});
