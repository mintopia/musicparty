<?php

use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rejects a second Request in the same active status for a Party', function (RequestStatus $status) {
    $party = Party::factory()->live()->create();
    $first = TrackRequest::factory()->for($party)->create(['status' => $status]);
    $second = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    expect(fn () => $second->forceFill(['status' => $status])->save())->toThrow(UniqueConstraintViolationException::class);

    expect($first->fresh()->status)->toBe($status)
        ->and($second->fresh()->status)->toBe(RequestStatus::Queued);
})->with([RequestStatus::UpNext, RequestStatus::Playing]);

it('allows one Up Next and one Playing Request per Party and one each across Parties', function () {
    $party = Party::factory()->live()->create();
    $other = Party::factory()->live()->create();

    foreach ([$party, $other] as $p) {
        TrackRequest::factory()->for($p)->create(['status' => RequestStatus::UpNext]);
        TrackRequest::factory()->for($p)->create(['status' => RequestStatus::Playing]);
        TrackRequest::factory()->for($p)->count(2)->create(['status' => RequestStatus::Played]);
    }

    expect(TrackRequest::query()->count())->toBe(8);
});

it('treats a constraint violation in SelectUpNext as already selected and rolls back', function () {
    $party = Party::factory()->live()->create();
    $queued = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    TrackRequest::saving(function (TrackRequest $request) use ($party) {
        if ($request->status === RequestStatus::UpNext && $request->party_id === $party->id) {
            DB::table('track_requests')->insert([
                ...TrackRequest::factory()->for($party)->make(['status' => RequestStatus::UpNext])->getAttributes(),
                'artists' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    expect(app(SelectUpNext::class)($party))->toBeNull()
        ->and($queued->fresh()->status)->toBe(RequestStatus::Queued)
        ->and(TrackRequest::query()->where('status', RequestStatus::UpNext)->count())->toBe(0);

    TrackRequest::flushEventListeners();
});
