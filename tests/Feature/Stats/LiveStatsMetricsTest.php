<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Stats\Actions\GetPartyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function scrapeLiveStats(): string
{
    app()->forgetScopedInstances();
    config(['prometheus.token' => 'scrape-token']);

    return test()->withToken('scrape-token')->get('/'.ltrim(config('prometheus.urls.default'), '/'))->assertOk()->getContent();
}

function metricsPlayed(PartyMember $member, string $title, int $durationMs = 180000): TrackRequest
{
    $request = TrackRequest::factory()->create([
        'party_id' => $member->party_id,
        'party_member_id' => $member->id,
        'status' => RequestStatus::Played,
        'title' => $title,
        'artists' => ['Artist One', 'Artist Two'],
        'duration_ms' => $durationMs,
        'started_at' => now(),
    ]);
    Play::factory()->create([
        'party_id' => $request->party_id,
        'track_request_id' => $request->id,
        'party_member_id' => $member->id,
        'provider_track_id' => $request->provider_track_id,
        'title' => $title,
        'artists' => $request->artists,
        'duration_ms' => $durationMs,
    ]);

    return $request;
}

function scriptedMetricsParty(string $code, array $state = []): Party
{
    $party = Party::factory()->live()->create(['code' => $code, ...$state]);
    $host = PartyMember::factory()->for($party)->host()->create();
    $alice = PartyMember::factory()->for($party)->create();
    PartyMember::factory()->for($party)->create(['banned' => true]);

    $loved = metricsPlayed($alice, 'Loved Song');
    $hated = metricsPlayed($host, 'Hated Song', 60000);
    RequestVote::factory()->create(['track_request_id' => $loved->id, 'party_member_id' => $host->id, 'value' => 1]);
    RequestVote::factory()->create(['track_request_id' => $hated->id, 'party_member_id' => $alice->id, 'value' => -1]);
    TrackRequest::factory()->count(2)->create(['party_id' => $party->id, 'party_member_id' => $alice->id, 'status' => RequestStatus::Queued]);
    TrackRequest::factory()->create(['party_id' => $party->id, 'party_member_id' => $alice->id, 'status' => RequestStatus::Pending]);

    return $party;
}

it('exports a scripted Party with the figures the Stats page returns', function () {
    $party = scriptedMetricsParty('ABCD');
    $stats = app(GetPartyStats::class)($party);

    $output = scrapeLiveStats();

    expect($output)
        ->toContain('musicparty_parties{state="live"} 1')
        ->toContain('musicparty_parties{state="ended"} 0')
        ->toContain('musicparty_party_members{party="ABCD"} 2')
        ->toContain('musicparty_party_queue_length{party="ABCD"} 2')
        ->toContain('musicparty_party_time_played_seconds{party="ABCD"} '.($stats['total_time_played_ms'] / 1000));

    foreach ($stats['top_tracks'] as $index => $row) {
        $rank = $index + 1;
        expect($output)->toContain("musicparty_party_top_track_plays{party=\"ABCD\",rank=\"{$rank}\",track=\"{$row['title']} — Artist One, Artist Two\"} {$row['plays']}");
    }
    foreach ($stats['top_requesters'] as $index => $row) {
        $rank = $index + 1;
        expect($output)->toContain("musicparty_party_top_requester_plays{party=\"ABCD\",rank=\"{$rank}\",member=\"{$row['nickname']}\"} {$row['plays']}");
    }
    expect($stats['most_upvoted'])->not->toBeEmpty()->and($stats['most_downvoted'])->not->toBeEmpty();
    expect($output)
        ->toContain('musicparty_party_most_upvoted_score{party="ABCD",rank="1",track="Loved Song — Artist One, Artist Two"} 1')
        ->toContain('musicparty_party_most_downvoted_score{party="ABCD",rank="1",track="Hated Song — Artist One, Artist Two"} -1');
});

it('exports Paused Parties and omits Ended Parties from party-labelled series', function () {
    scriptedMetricsParty('PAUS', ['state' => 'paused']);
    scriptedMetricsParty('DONE', ['state' => 'ended']);

    $output = scrapeLiveStats();

    expect($output)
        ->toContain('musicparty_party_members{party="PAUS"} 2')
        ->toContain('musicparty_parties{state="paused"} 1')
        ->toContain('musicparty_parties{state="ended"} 1')
        ->not->toContain('party="DONE"');
});

it('exports a Live Party with no stats row yet as zeroes with no top series', function () {
    Party::factory()->live()->create(['code' => 'NEWP']);

    $output = scrapeLiveStats();

    expect($output)
        ->toContain('musicparty_party_members{party="NEWP"} 0')
        ->toContain('musicparty_party_time_played_seconds{party="NEWP"} 0')
        ->not->toContain('musicparty_party_top_track_plays{');
});

it('runs the same number of queries however many Parties exist', function () {
    scriptedMetricsParty('ONE1');
    DB::enableQueryLog();
    scrapeLiveStats();
    $single = count(DB::getQueryLog());

    foreach (['TWO2', 'TRE3', 'FOR4', 'FIV5'] as $code) {
        scriptedMetricsParty($code);
    }
    DB::flushQueryLog();
    scrapeLiveStats();
    $many = count(DB::getQueryLog());

    expect($many)->toBe($single);
});
