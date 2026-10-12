<?php

use App\Domain\Admin\Actions\EnsureSocialProvider;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Data\PlayerCommand;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Testing\FakePlayer;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider as SocialiteDriver;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;
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

function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return $count;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function seedDiscord(array $overrides = []): SocialProvider
{
    config(['services.discord.client_id' => 'client-id', 'services.discord.client_secret' => 'client-secret']);
    test()->artisan('providers:seed')->assertSuccessful();

    return tap(SocialProvider::query()->where('code', 'discord')->firstOrFail(), fn (SocialProvider $provider) => $provider->forceFill($overrides)->save());
}

function configureSpotifyCredentials(string $app = 'spotify', string $id = 'client-id', string $secret = 'client-secret'): SocialProvider
{
    $provider = app(EnsureSocialProvider::class)->handle($app);

    foreach (['client_id' => $id, 'client_secret' => $secret] as $code => $value) {
        $provider->settings->firstWhere('code', $code)?->forceFill(['value' => $value])->save();
    }

    app()->forgetScopedInstances();

    return $provider->refresh();
}

function blankSpotifyCredential(string $code, string $app = 'spotify'): void
{
    app(EnsureSocialProvider::class)->handle($app)->settings->firstWhere('code', $code)?->forceFill(['value' => null])->save();

    app()->forgetScopedInstances();
}

function remoteUser(string $id = '1001', string $nickname = 'ravebot'): SocialiteUser
{
    return (new SocialiteUser)->map([
        'id' => $id,
        'nickname' => $nickname,
        'name' => 'Rave Bot',
        'email' => 'rave@example.test',
        'avatar' => 'https://cdn.example.test/a.png',
    ])->setToken('access-token')->setRefreshToken('refresh-token');
}

function mockDriver(): MockInterface
{
    $driver = Mockery::mock(SocialiteDriver::class);
    Socialite::shouldReceive('buildProvider')->andReturn($driver);

    return $driver;
}
