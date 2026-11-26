<?php

use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PlayedSong;
use App\Models\Party;
use App\Models\Role;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use Carbon\CarbonImmutable;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Support\Str;

function dashboardTrack(string $id): Track
{
    return new Track('spotify', $id, "Track {$id}", 180000, false, true, new AlbumRef("album-{$id}", "Album {$id}", null), [new ArtistRef("artist-{$id}", "Artist {$id}")]);
}

function dashboardFake(): FakeMusicProvider
{
    return app(FakeMusicProvider::class);
}

function dashboardParty(array $attributes = []): Party
{
    test()->seed(PartyMemberRoleSeeder::class);
    $owner = User::factory()->create(['nickname' => 'Kestrel', 'first_login' => false]);

    return Party::factory()->create(array_merge(['user_id' => $owner->id, 'provider_id' => 'spotify', 'active' => true], $attributes));
}

function dashboardUser(Party $party, ?string $role): User
{
    $user = User::factory()->create(['first_login' => false]);
    if ($role === 'admin') {
        $user->roles()->attach(Role::query()->where('code', 'admin')->first() ?? Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    } elseif ($role !== null) {
        $member = new PartyMember;
        $member->party_id = $party->id;
        $member->user_id = $user->id;
        $member->role_id = PartyMemberRole::query()->where('code', $role)->firstOrFail()->id;
        $member->save();
    }

    return $user;
}

function dashboardRequest(Party $party, array $attributes = [], ?string $trackId = null): UpcomingSong
{
    $song = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => $trackId ?? Str::random(22)]);
    dashboardFake()->withTrack(dashboardTrack($song->provider_track_id));

    return UpcomingSong::factory()->create(array_merge(['party_id' => $party->id, 'song_id' => $song->id], $attributes));
}

function dashboardPlayed(UpcomingSong $request, ?Party $party = null): PlayedSong
{
    return PlayedSong::factory()->create(['party_id' => $request->party_id, 'song_id' => $request->song_id, 'upcoming_song_id' => $request->id, 'played_at' => now()]);
}

function dashboardPlaying(string $trackId, bool $playing = true, int $progressMs = 1000, ?string $contextUri = null): PlaybackState
{
    return new PlaybackState($playing, $trackId, null, 180000, $progressMs, CarbonImmutable::now(), false, 'off', 'fake-device', 'Fake Speaker', PlaybackSource::Polling, [], null, $contextUri);
}
