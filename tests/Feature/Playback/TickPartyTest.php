<?php

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Jobs\TickParty;
use App\Domain\Playback\Jobs\TickPlayback;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => CarbonImmutable::setTestNow('2026-01-01 12:00:00'));

afterEach(fn () => CarbonImmutable::setTestNow());

it('dispatches one tick per live party with a player on the ticks queue', function () {
    Queue::fake();
    $live = Party::factory()->live()->count(3)->create();
    Party::factory()->create();
    Party::factory()->live()->create(['player_kind' => null]);

    (new TickPlayback)->handle();

    Queue::assertPushedTimes(TickParty::class, 3);
    Queue::assertPushedOn('ticks', TickParty::class, fn (TickParty $job): bool => $live->pluck('code')->contains($job->partyCode));
});

it('does not stack a second tick for a party whose job is still pending', function () {
    Queue::fake();
    $busy = Party::factory()->live()->create();
    $other = Party::factory()->live()->create();
    Cache::lock('laravel_unique_job:'.TickParty::class.':'.$busy->code, 30)->get();

    foreach ([$busy, $other] as $party) {
        TickParty::dispatch($party->code);
    }

    Queue::assertPushedTimes(TickParty::class, 1);
    Queue::assertPushed(TickParty::class, fn (TickParty $job): bool => $job->partyCode === $other->code);
});

it('skips a party whose overlap lock is held and still ticks the others', function () {
    $held = livePlaybackParty();
    $free = livePlaybackParty();
    useFakePlayer($held);
    $player = useFakePlayer($free);
    TrackRequest::factory()->for($held)->create(['provider_track_id' => 'h1']);
    TrackRequest::factory()->for($free)->create(['provider_track_id' => 'f1']);
    Cache::lock('laravel-queue-overlap:'.TickParty::class.":tick:{$held->code}", 30)->get();

    foreach ([$held, $free] as $party) {
        dispatch_sync(new TickParty($party->code));
    }

    expect(TrackRequest::query()->where('party_id', $held->id)->first()?->status)->toBe(RequestStatus::Queued)
        ->and($player->state()->currentTrack?->providerTrackId)->toBe('f1');
});

it('frees the overlap lock after it expires', function () {
    $party = livePlaybackParty();
    useFakePlayer($party);
    TrackRequest::factory()->for($party)->create(['provider_track_id' => 'r1']);
    Cache::lock('laravel-queue-overlap:'.TickParty::class.":tick:{$party->code}", 30)->get();

    dispatch_sync(new TickParty($party->code));
    expect(TrackRequest::query()->where('party_id', $party->id)->first()?->status)->toBe(RequestStatus::Queued);

    $this->travel(31)->seconds();

    dispatch_sync(new TickParty($party->code));
    expect(TrackRequest::query()->where('party_id', $party->id)->first()?->status)->not->toBe(RequestStatus::Queued);
});

it('keeps ticking other parties when one party tick throws', function () {
    Bus::fake();
    $broken = Party::factory()->live()->create();
    $healthy = Party::factory()->live()->create();
    $this->mock(PlaybackCoordinator::class)
        ->shouldReceive('tick')
        ->twice()
        ->andReturnUsing(function (Party $party) use ($broken): void {
            if ($party->is($broken)) {
                throw new RuntimeException('boom');
            }
        });

    expect(fn () => new TickParty($broken->code)->handle(app(PlaybackCoordinator::class)))->toThrow(RuntimeException::class);
    new TickParty($healthy->code)->handle(app(PlaybackCoordinator::class));
});

it('declares its guards', function () {
    $job = new TickParty('ABC123');

    expect($job->queue)->toBe('ticks')
        ->and($job->timeout)->toBe(20)
        ->and($job->uniqueFor)->toBe(30)
        ->and($job->uniqueId())->toBe('ABC123')
        ->and($job->middleware()[0]->expiresAfter)->toBe(30);
});

it('makes no spotify calls over 60 idle ticks of a polling party', function () {
    Http::fake();
    $party = Party::factory()->live()->create(['player_kind' => 'polling', 'fallback_playlist_id' => null]);
    TrackRequest::factory()->for($party)->create([
        'status' => RequestStatus::Playing,
        'started_at' => now(),
        'duration_ms' => 600000,
    ]);

    foreach (range(1, 60) as $_) {
        dispatch_sync(new TickParty($party->code));
    }

    Http::assertNothingSent();
});
