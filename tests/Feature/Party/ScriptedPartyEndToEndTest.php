<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Queue\RequestStatus;
use App\Jobs\BroadcastPartyQueue;
use App\Jobs\StartPlayback;
use App\Models\Party;
use App\Models\Play;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake([StartPlayback::class, BroadcastPartyQueue::class]);
});

it('runs a scripted Party on the Fake Provider and Fake Player from creation through Ended and Export', function () {
    $playlist = array_map(
        fn (int $n): TrackData => new TrackData(
            'fake', "fb{$n}", "Fallback {$n}", [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'),
            180000, false, null, [], true,
        ),
        range(1, 20),
    );
    $requestable = array_map(
        fn (int $n): TrackData => new TrackData(
            'fake', "track-{$n}", "Request {$n}", [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'),
            180000, false, null, [], true,
        ),
        [1, 2],
    );
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider([...$requestable, ...$playlist], playlistTracks: ['pl' => $playlist]));

    $host = User::factory()->withRole('create-party')->create(['nickname' => 'Hosty']);
    $alice = User::factory()->create(['nickname' => 'Alice']);
    $bob = User::factory()->create(['nickname' => 'Bob']);

    Sanctum::actingAs($host);
    $code = $this->postJson('/api/v1/parties', ['name' => 'Scripted Night', 'music_provider' => 'fake', 'player_kind' => 'fake'])
        ->assertCreated()->json('data.code');
    $party = Party::query()->where('code', $code)->firstOrFail();
    $party->forceFill(['fallback_playlist_id' => 'pl'])->save();
    $player = useFakePlayer($party, FeedMode::Ahead);

    foreach ([$alice, $bob] as $guest) {
        Sanctum::actingAs($guest);
        $this->postJson("/api/v1/parties/{$code}/join")->assertOk();
    }

    Sanctum::actingAs($host);
    $this->postJson("/api/v1/parties/{$code}/live")->assertOk()->assertJsonPath('data.state', 'live');

    Sanctum::actingAs($alice);
    $this->postJson("/api/v1/parties/{$code}/requests", ['provider_track_id' => 'track-1'])->assertCreated();
    Sanctum::actingAs($bob);
    $this->postJson("/api/v1/parties/{$code}/requests", ['provider_track_id' => 'track-2'])->assertCreated();
    $second = TrackRequest::query()->where('provider_track_id', 'track-2')->firstOrFail();
    Sanctum::actingAs($alice);
    $this->putJson("/api/v1/parties/{$code}/requests/{$second->id}/vote", ['value' => 'up'])->assertOk();

    $coordinator = app(PlaybackCoordinator::class);
    $coordinator->startIfIdle($party);
    $player->advance();
    $player->advance();

    expect(Play::query()->where('party_id', $party->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Played)->count())->toBeGreaterThanOrEqual(2);

    $play = Play::query()->where('party_id', $party->id)->orderBy('id')->firstOrFail();
    Sanctum::actingAs($bob);
    $this->putJson("/api/v1/parties/{$code}/plays/{$play->id}/rating", ['value' => 'up'])->assertOk();

    Sanctum::actingAs($host);
    $this->postJson("/api/v1/parties/{$code}/end")->assertOk()->assertJsonPath('data.state', 'ended');

    $export = $this->getJson("/api/v1/parties/{$code}/export")->assertOk()->json();
    expect($export)->toHaveKeys(['schema_version', 'party', 'members', 'requests', 'plays'])
        ->and($export['party']['code'])->toBe($code)
        ->and($export['plays'])->not->toBeEmpty();

    Sanctum::actingAs($alice);
    $this->getJson("/api/v1/parties/{$code}/export")->assertForbidden();
});
