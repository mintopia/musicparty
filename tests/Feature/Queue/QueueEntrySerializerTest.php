<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\SelectionMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\ScoreBoostMod;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->trusted = PartyMember::factory()->for($this->party)->create();

    $this->leader = queuedWithVotes($this->party, 'Leader', 2);
    $this->boosted = queuedWithVotes($this->party, 'Boosted', 1, $this->trusted);
    $this->plain = queuedWithVotes($this->party, 'Plain', 0);

    ModFixtures::enable($this->party, new ScoreBoostMod(memberId: $this->trusted->id, boost: 3));
});

function queuedWithVotes(Party $party, string $title, int $votes, ?PartyMember $requester = null): TrackRequest
{
    $request = TrackRequest::factory()->for($party)->create([
        'status' => RequestStatus::Queued,
        'title' => $title,
        'party_member_id' => ($requester ?? PartyMember::factory()->for($party)->create())->id,
    ]);

    RequestVote::factory()->count($votes)->create([
        'track_request_id' => $request->id,
        'party_member_id' => fn () => PartyMember::factory()->for($party),
        'value' => 1,
    ]);

    return $request;
}

/**
 * @param  list<array<string, mixed>>  $entries
 * @return list<array{int, int}>
 */
function idsAndScores(array $entries): array
{
    return array_map(fn (array $entry): array => [$entry['id'], $entry['score']], $entries);
}

it('shows the same Requests in the same order with the same Scores on the API, page props and broadcast', function () {
    Sanctum::actingAs($this->user);

    $expected = [[$this->boosted->id, 4], [$this->leader->id, 2], [$this->plain->id, 0]];

    $api = $this->getJson('/api/v1/parties/ABCD/queue')->assertOk()->json('data');
    $broadcast = app(PartyQueueSnapshot::class)->build($this->party)['queue'];

    $props = null;
    $this->withoutVite()->get('/parties/abcd')->assertOk()
        ->assertInertia(function (Assert $page) use (&$props): void {
            $props = $page->toArray()['props']['queue'];
        });

    expect(idsAndScores($api))->toBe($expected)
        ->and(idsAndScores($props))->toBe($expected)
        ->and(idsAndScores($broadcast))->toBe($expected);
});

it('lets deterministic selection pick the first listed Request', function () {
    $first = app(PartyQueueSnapshot::class)->build($this->party)['queue'][0]['id'];

    expect(app(SelectUpNext::class)($this->party)?->id)->toBe($first)
        ->and($first)->toBe($this->boosted->id);
});

it('breaks Score ties by request time then id', function () {
    $older = $this->plain;
    $older->forceFill(['created_at' => now()->subHour()])->save();
    $tiedWithOlder = queuedWithVotes($this->party, 'Tied', 0);
    $tiedWithOlder->forceFill(['created_at' => $older->created_at])->save();

    $ids = array_column(app(PartyQueueSnapshot::class)->build($this->party)['queue'], 'id');

    expect(array_slice($ids, -2))->toBe([$older->id, $tiedWithOlder->id]);
});

it('exposes selection_mode on the Party resource and page props', function () {
    $this->party->forceFill(['selection_mode' => SelectionMode::Weighted])->save();
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/parties/ABCD')->assertOk()->assertJsonPath('data.selection_mode', 'weighted');
    $this->withoutVite()->get('/parties/abcd')->assertInertia(fn (Assert $page): Assert => $page->where('party.selection_mode', 'weighted'));
});

it('matches the AsyncAPI queue.updated entry schema to the presenter output', function () {
    $spec = json_decode((string) file_get_contents(base_path('asyncapi/asyncapi.json')), true, flags: JSON_THROW_ON_ERROR);
    $entrySchema = $spec['components']['messages']['Party.QueueUpdatedEvent']['payload']['properties']['queue']['items'];
    $entry = app(PartyQueueSnapshot::class)->build($this->party)['queue'][0];

    expect(array_keys($entry))->toEqualCanonicalizing($entrySchema['required'])
        ->and(array_keys($entry))->toEqualCanonicalizing(array_keys($entrySchema['properties']));
});
