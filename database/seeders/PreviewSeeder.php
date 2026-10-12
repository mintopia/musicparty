<?php

namespace Database\Seeders;

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\Rating;
use App\Models\RequestVote;
use App\Models\Role;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class PreviewSeeder extends Seeder
{
    /** @return list<array{string, string, string, int, bool}> */
    private static function catalogue(): array
    {
        return [
            ['Neon Skyline', 'Pixel Harbour', 'Arcade Dreams', 214000, false],
            ['Lag Spike', 'Ping Floyd', 'Latency', 187000, true],
            ['Respawn Point', 'The Frag Pack', 'Checkpoint', 203000, false],
            ['Cable Management', 'Rack Unit', 'Patch Panel', 226000, false],
            ['Overclocked Heart', 'Pixel Harbour', 'Arcade Dreams', 241000, false],
            ['Dial-Up Serenade', 'Ping Floyd', 'Latency', 173000, false],
            ['Gigabit Glow', 'Rack Unit', 'Patch Panel', 198000, false],
            ['Final Boss Energy', 'The Frag Pack', 'Checkpoint', 256000, true],
            ['LAN of the Free', 'Switch & The Hubs', 'Broadcast Storm', 209000, false],
            ['Ctrl Alt Delight', 'Switch & The Hubs', 'Broadcast Storm', 192000, false],
            ['Midnight Patch Notes', 'Pixel Harbour', 'Hotfix', 232000, false],
            ['Rubber Duck Debugger', 'Rack Unit', 'Patch Panel', 181000, false],
            ['Pwned', 'The Frag Pack', 'Checkpoint', 167000, true],
            ['Sudo Make Me a Sandwich', 'Switch & The Hubs', 'Broadcast Storm', 219000, false],
            ['Low Poly Love', 'Pixel Harbour', 'Hotfix', 205000, false],
            ['Bandwidth', 'Ping Floyd', 'Latency', 248000, false],
            ['Hot Swap', 'Rack Unit', 'Patch Panel', 176000, false],
            ['Press Start', 'The Frag Pack', 'Checkpoint', 189000, false],
        ];
    }

    public static function catalogueProvider(): FakeMusicProvider
    {
        $tracks = [];
        foreach (self::catalogue() as $i => [$title, $artist, $album, $ms, $explicit]) {
            $tracks[] = new TrackData('fake', 'track-'.($i + 1), $title, [new ArtistData('artist-'.crc32($artist), $artist)], new AlbumData('album-'.crc32($album), $album), $ms, $explicit, 'ISRC'.str_pad((string) ($i + 1), 7, '0', STR_PAD_LEFT), []);
        }

        return new FakeMusicProvider($tracks);
    }

    public function run(): void
    {
        $this->call([RolesSeeder::class, SettingsSeeder::class]);

        $admin = User::factory()->create(['nickname' => 'admin']);
        $admin->roles()->attach(Role::whereCode('admin')->first());
        $admin->roles()->attach(Role::whereCode('create-party')->first());
        $host = User::factory()->create(['nickname' => 'host']);
        $host->roles()->attach(Role::whereCode('create-party')->first());
        $member = User::factory()->create(['nickname' => 'member']);
        $others = collect(['Zed', 'Kira', 'Moss', 'Tango', 'Vex'])->map(fn (string $n) => User::factory()->create(['nickname' => $n]));

        $party = Party::factory()->live()->create(['code' => 'LANX', 'name' => 'Friday LAN Night', 'user_id' => $host->id]);
        $hostMember = PartyMember::factory()->host()->create(['party_id' => $party->id, 'user_id' => $host->id]);
        $memberMember = PartyMember::factory()->create(['party_id' => $party->id, 'user_id' => $member->id]);
        $adminMember = PartyMember::factory()->moderator()->create(['party_id' => $party->id, 'user_id' => $admin->id]);
        $guests = $others->map(fn (User $u) => PartyMember::factory()->create(['party_id' => $party->id, 'user_id' => $u->id]));
        $everyone = collect([$hostMember, $memberMember, $adminMember])->merge($guests);

        Party::factory()->create(['code' => 'ZZZZ', 'name' => 'Sunday Wind-down', 'user_id' => $host->id]);

        $catalogue = self::catalogue();
        $make = function (int $index, RequestStatus $status, PartyMember $by, array $extra = []) use ($party, $catalogue): TrackRequest {
            [$title, $artist, $album, $ms, $explicit] = $catalogue[$index];

            return TrackRequest::factory()->create([
                'party_id' => $party->id, 'party_member_id' => $by->id, 'provider_track_id' => 'track-'.($index + 1),
                'title' => $title, 'artists' => [$artist], 'album' => $album, 'duration_ms' => $ms, 'explicit' => $explicit, 'status' => $status,
            ] + $extra);
        };

        foreach (range(0, 5) as $i) {
            $by = $everyone[$i % $everyone->count()];
            $request = $make($i, RequestStatus::Played, $by, ['started_at' => now()->subMinutes(60 - $i * 9), 'decided_at' => now()->subMinutes(70 - $i * 9)]);
            $play = Play::factory()->create([
                'party_id' => $party->id, 'track_request_id' => $request->id, 'party_member_id' => $by->id,
                'provider_track_id' => $request->provider_track_id, 'title' => $request->title, 'artists' => $request->artists,
                'album' => $request->album, 'duration_ms' => $request->duration_ms, 'explicit' => $request->explicit,
                'played_at' => now()->subMinutes(60 - $i * 9), 'selection_score' => random_int(1, 6),
            ]);
            foreach ($everyone->shuffle()->take(4) as $rater) {
                Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => $rater->id, 'value' => random_int(0, 4) ? 1 : -1]);
            }
        }

        $make(6, RequestStatus::Playing, $guests[0], ['started_at' => now()->subSeconds(70), 'decided_at' => now()->subMinutes(5)]);
        $make(7, RequestStatus::UpNext, $memberMember, ['up_next_at' => now()->subMinutes(1), 'decided_at' => now()->subMinutes(4)]);

        foreach (range(8, 15) as $n => $i) {
            $request = $make($i, RequestStatus::Queued, $everyone[($n + 2) % $everyone->count()], ['decided_at' => now()->subMinutes(20 - $n)]);
            foreach ($everyone->shuffle()->take(random_int(1, 6)) as $voter) {
                RequestVote::factory()->create(['track_request_id' => $request->id, 'party_member_id' => $voter->id, 'value' => random_int(0, 4) ? 1 : -1]);
            }
        }

        $make(16, RequestStatus::Pending, $guests[2]);
        $make(17, RequestStatus::Pending, $guests[3]);

        $party->update(['state' => PartyState::Live]);
    }
}
