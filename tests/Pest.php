<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Data\PlayerCommand;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Testing\FakePlayer;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit/Domain/Music', 'Unit/Domain/Playback');

function playbackTrack(int $n, int $durationMs = 180000, bool $explicit = false): TrackData
{
    return new TrackData(
        'fake', "p{$n}", "Track {$n}", [new ArtistData('a', 'Artist')],
        new AlbumData('al', 'Album'), $durationMs, $explicit,
    );
}

/**
 * @param  list<TrackData>  $playlist
 * @param  array<string, mixed>  $attributes
 */
function livePlaybackParty(array $playlist = [], array $attributes = []): Party
{
    app()->instance(
        FakeMusicProvider::class,
        new FakeMusicProvider($playlist, playlistTracks: ['pl' => $playlist]),
    );

    return Party::factory()->live()->create(['fallback_playlist_id' => 'pl', ...$attributes]);
}

function useFakePlayer(Party $party, FeedMode $mode = FeedMode::Ahead): FakePlayer
{
    $player = new FakePlayer(feedMode: $mode);
    app(PartyPlayers::class)->register($party, $player);

    return $player;
}

/**
 * @return list<string>
 */
function enqueuedTrackIds(FakePlayer $player): array
{
    return array_values(array_map(
        fn (PlayerCommand $command): ?string => $command->providerTrackId,
        array_filter($player->commands(), fn (PlayerCommand $command): bool => $command->type === 'enqueue'),
    ));
}
