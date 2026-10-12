<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\CheapRequestRule;
use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Mod\EnabledMods;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\BlocklistMatchType;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'explicit' => true]);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

function searchHit(string $trackId): array
{
    $hits = test()->getJson('/api/v1/parties/ABCD/search?q=a')->assertOk()->json('data');

    return collect($hits)->firstWhere('provider_track_id', $trackId);
}

it('marks every result eligible when no rule blocks it', function () {
    expect(searchHit('track-1'))->toMatchArray(['eligible' => true, 'reason' => null, 'queued_request_id' => null, 'my_vote' => 0]);
});

it('gives the reason for a track that is too long', function () {
    $this->party->forceFill(['max_song_length' => 170])->save();

    expect(searchHit('track-1'))->toMatchArray(['eligible' => false])
        ->and(searchHit('track-1')['reason'])->toContain('170');
});

it('refuses explicit tracks when the party disallows them', function () {
    $this->party->forceFill(['explicit' => false])->save();

    expect(searchHit('track-2')['eligible'])->toBeFalse()
        ->and(searchHit('track-1')['eligible'])->toBeTrue();
});

it('refuses blocklisted, up-next and recently played tracks', function () {
    $this->party->forceFill(['no_repeat_interval' => 3600])->save();
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::UpNext, 'provider_track_id' => 'track-1']);
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Played, 'provider_track_id' => 'track-2']);
    BlocklistEntry::factory()->for($this->party)->create(['match_type' => BlocklistMatchType::TrackId, 'value' => 'track-3']);

    expect(searchHit('track-1')['eligible'])->toBeFalse()
        ->and(searchHit('track-2')['eligible'])->toBeFalse()
        ->and(searchHit('track-3')['eligible'])->toBeFalse();
});

it('refuses every result when the member is at their request limit', function () {
    $this->party->forceFill(['max_requests' => 1])->save();
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'party_member_id' => $this->member->id]);

    expect(searchHit('track-1')['eligible'])->toBeFalse();
});

it('returns the queued request id and the member vote for a queued track', function () {
    $request = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'provider_track_id' => 'track-1']);
    RequestVote::factory()->create(['track_request_id' => $request->id, 'party_member_id' => $this->member->id, 'value' => -1]);

    expect(searchHit('track-1'))->toMatchArray(['queued' => true, 'eligible' => true, 'queued_request_id' => $request->id, 'my_vote' => -1, 'score' => -1]);
});

it('uses a constant number of queries however many results are queued or blocklisted', function () {
    $this->party->forceFill(['max_song_length' => 170, 'max_requests' => 5, 'no_repeat_interval' => 3600])->save();
    $search = fn () => $this->getJson('/api/v1/parties/ABCD/search?q=a')->assertOk();

    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'provider_track_id' => 'track-4', 'party_member_id' => $this->member->id]);
    $search();
    $before = countQueries($search);

    foreach (['track-1', 'track-2', 'track-3'] as $trackId) {
        TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'provider_track_id' => $trackId]);
        BlocklistEntry::factory()->for($this->party)->create(['match_type' => BlocklistMatchType::TrackId, 'value' => $trackId]);
    }

    expect(countQueries($search))->toBe($before);
});

it('gives the reason for a track that is too short', function () {
    $this->party->forceFill(['min_song_length' => 190])->save();

    expect(searchHit('track-1')['eligible'])->toBeFalse()
        ->and(searchHit('track-2')['eligible'])->toBeTrue();
});

it('refuses every result when requests are closed to the member', function (string $state) {
    match ($state) {
        'banned' => $this->member->forceFill(['banned' => true])->save(),
        'disabled' => $this->party->forceFill(['allow_requests' => false])->save(),
        'ended' => $this->party->forceFill(['state' => PartyState::Ended])->save(),
    };

    expect(searchHit('track-1')['eligible'])->toBeFalse()
        ->and(searchHit('track-3')['eligible'])->toBeFalse();
})->with(['banned', 'disabled', 'ended']);

it('runs cheap mod rules and never a non-cheap one such as AI review', function () {
    $cheap = new class implements CheapRequestRule
    {
        public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
        {
            return $track->providerTrackId === 'track-1' ? RuleVerdict::reject('Too cheerful.') : RuleVerdict::accept();
        }
    };
    $expensive = new class implements RequestRule
    {
        public int $calls = 0;

        public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
        {
            $this->calls++;

            return RuleVerdict::reject('AI says no.');
        }
    };
    $mod = Mockery::mock(Mod::class);
    $mod->shouldReceive('requestRules')->andReturn([$expensive, $cheap]);
    $mod->shouldReceive('name')->andReturn('Mood');
    $this->mock(EnabledMods::class)->shouldReceive('for')->andReturn([new EnabledMod($mod, new ModContext('mood', $this->party, []))]);

    $first = searchHit('track-1');

    expect($first['eligible'])->toBeFalse()
        ->and($first['reason'])->toContain('Too cheerful.')
        ->and(searchHit('track-3')['eligible'])->toBeTrue()
        ->and($expensive->calls)->toBe(0);
});
