<?php

namespace Database\Seeders;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Database\Seeder;

class E2eTvPartySeeder extends Seeder
{
    public const PARTY_CODE = 'ETVE';

    public function run(): void
    {
        Party::query()->where('code', self::PARTY_CODE)->delete();

        $party = Party::factory()->live()->create(['code' => self::PARTY_CODE, 'name' => 'Friday Night LAN']);
        $member = PartyMember::factory()->create(['party_id' => $party->id]);

        TrackRequest::factory()->status(RequestStatus::Playing)->create([
            'party_id' => $party->id,
            'party_member_id' => $member->id,
            'title' => 'Dance Floor Gravity With A Remarkably Long Title For Wrapping',
            'artists' => ['Nova Kids', 'Another Very Long Artist Name'],
            'album' => 'Orbit',
            'duration_ms' => 241000,
            'started_at' => now()->subSeconds(162),
        ]);
        TrackRequest::factory()->status(RequestStatus::UpNext)->create([
            'party_id' => $party->id,
            'party_member_id' => $member->id,
            'title' => 'Lights Out, Fans Up',
            'artists' => ['LAN Party Allstars'],
            'album' => 'Frag Limit',
        ]);
    }
}
