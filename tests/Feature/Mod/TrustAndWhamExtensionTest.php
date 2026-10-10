<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\SchedulerMod;
use Tests\Fixtures\Mods\ScoreBoostMod;

uses(RefreshDatabase::class);

it('supports trust-score and Whamageddon style Mods together with no core changes', function () {
    Queue::fake();
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $party = Party::factory()->live()->create();
    $trusted = PartyMember::factory()->for($party)->create();
    $plain = PartyMember::factory()->for($party)->create();
    ModFixtures::enable($party, new ScoreBoostMod('trust-score', $trusted->id, 3));
    ModFixtures::enable($party, new SchedulerMod('whamageddon', 'track-3', bypassRules: true));

    $favoured = TrackRequest::factory()->create(['party_id' => $party->id, 'party_member_id' => $trusted->id]);
    $popular = TrackRequest::factory()->create(['party_id' => $party->id, 'party_member_id' => $plain->id]);
    RequestVote::factory()->count(2)->create(['track_request_id' => $popular->id, 'party_member_id' => fn () => PartyMember::factory()->for($party), 'value' => 1]);

    expect(app(RunScheduledActions::class)($party))->toBe(1)
        ->and(TrackRequest::query()->whereNull('party_member_id')->where('provider_track_id', 'track-3')->exists())->toBeTrue()
        ->and(app(SelectUpNext::class)($party)->is($favoured))->toBeTrue();
});
