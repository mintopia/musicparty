<?php

use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Exceptions\VoteRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);

function downvoteInOwnProcess(string $connection, string $database, int $partyId, int $memberId, int $requestId): Closure
{
    return function () use ($connection, $database, $partyId, $memberId, $requestId): string {
        config(["database.connections.{$connection}.database" => $database]);
        DB::purge($connection);

        try {
            (new VoteOnRequest)(
                Party::query()->findOrFail($partyId),
                PartyMember::query()->findOrFail($memberId),
                TrackRequest::query()->findOrFail($requestId),
                VoteDirection::Down,
            );

            return 'accepted';
        } catch (VoteRefusedException) {
            return 'refused';
        }
    };
}

beforeEach(function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Row locking needs a MySQL-family database.');
    }
});

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

it('holds the per-hour downvote cap under parallel downvotes by one member', function () {
    $cap = 3;
    $attempts = 10;

    $party = Party::factory()->live()->create(['downvotes' => true, 'downvotes_per_hour' => $cap]);
    $member = PartyMember::factory()->for($party)->create();
    $requestIds = TrackRequest::factory()->count($attempts)->for($party)->create(['status' => RequestStatus::Queued])->pluck('id')->all();

    $database = config('database.connections.'.config('database.default').'.database');
    $connection = config('database.default');

    $outcomes = Concurrency::driver('process')->run(
        array_map(fn (int $requestId): Closure => downvoteInOwnProcess($connection, $database, $party->id, $member->id, $requestId), $requestIds),
    );

    expect(array_count_values($outcomes))->toEqual(['accepted' => $cap, 'refused' => $attempts - $cap])
        ->and(RequestVote::query()->where('party_member_id', $member->id)->where('value', -1)->count())->toBe($cap);
});
