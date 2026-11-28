<?php

use App\Exceptions\ProviderBackoffException;
use App\Jobs\EmptyContextPlaylist;
use App\Jobs\PartyUpdate;
use App\Jobs\RefreshFallbackPlaylist;
use App\Models\Party;
use App\Services\FallbackPlaylist;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Playback\ContextPlaylist;
use App\Services\PlaybackSync;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;

function backoffException(int $seconds): Throwable
{
    return new class($seconds) extends RuntimeException implements ProviderBackoffException
    {
        public function __construct(private readonly int $seconds)
        {
            parent::__construct('backoff');
        }

        public function retryAfterSeconds(): int
        {
            return $this->seconds;
        }
    };
}

function queueJobMock(): Job
{
    $queueJob = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $queueJob->shouldReceive('attempts')->andReturn(1);

    return $queueJob;
}

function pollingParty(array $attributes = []): Party
{
    $party = Party::factory()->create(['active' => false]);
    $party->forceFill(array_merge(['active' => true, 'poll' => true], $attributes))->saveQuietly();

    return $party->fresh();
}

it('releases for Retry-After up to 60s and returns above it', function (int $retryAfter, ?int $released) {
    $party = pollingParty();
    $sync = Mockery::mock(PlaybackSync::class);
    $sync->shouldReceive('sync')->andThrow(backoffException($retryAfter));
    app()->instance(PlaybackSync::class, $sync);

    $queueJob = queueJobMock();
    if ($released === null) {
        $queueJob->shouldNotReceive('release');
    } else {
        $queueJob->shouldReceive('release')->once()->with($released);
    }
    $job = new PartyUpdate($party);
    $job->setJob($queueJob);

    $job->handle();
})->with([
    'long Retry-After returns' => [3600, null],
    'just over the cap returns' => [61, null],
    'short Retry-After releases' => [30, 30],
    'cap releases' => [60, 60],
]);

it('bounds the release for every job that honours Retry-After', function (string $class) {
    $party = pollingParty();
    $fallback = Mockery::mock(FallbackPlaylist::class);
    $fallback->shouldReceive('refresh')->andThrow(backoffException(3600));
    app()->instance(FallbackPlaylist::class, $fallback);
    $context = Mockery::mock(ContextPlaylist::class);
    $sync = Mockery::mock(PlaybackSync::class);
    $sync->shouldReceive('withLock')->andThrow(backoffException(3600));

    $queueJob = queueJobMock();
    $queueJob->shouldNotReceive('release');
    $job = new $class($party);
    $job->setJob($queueJob);

    $class === EmptyContextPlaylist::class ? $job->handle($context, $sync) : $job->handle($fallback);
})->with([RefreshFallbackPlaylist::class, EmptyContextPlaylist::class]);

it('deletes party jobs for a deleted party and uses retry windows rather than try counts', function (string $class) {
    $job = new $class(Party::factory()->create());

    expect($job->deleteWhenMissingModels)->toBeTrue()
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp())
        ->and(property_exists($job, 'tries'))->toBeFalse();
})->with([PartyUpdate::class, RefreshFallbackPlaylist::class, EmptyContextPlaylist::class]);

it('releases a webhook update inside the debounce for 6s and drops a polling one', function (PlaybackSource $source, bool $released) {
    $party = pollingParty(['last_updated_at' => now()->subSecond()]);
    $sync = Mockery::mock(PlaybackSync::class);
    $sync->shouldNotReceive('sync');
    app()->instance(PlaybackSync::class, $sync);

    $queueJob = queueJobMock();
    if ($released) {
        $queueJob->shouldReceive('release')->once()->with(6);
    } else {
        $queueJob->shouldNotReceive('release');
    }
    $job = new PartyUpdate($party, $source);
    $job->setJob($queueJob);

    $job->handle();
})->with([
    'webhook' => [PlaybackSource::Webhook, true],
    'polling' => [PlaybackSource::Polling, false],
]);

it('skips the webhook dispatch when an update is already pending within 3s', function () {
    Queue::fake();
    Cache::flush();
    $party = pollingParty();

    $this->postJson(route('webhooks.parties.soloist', $party))->assertNoContent();
    $this->postJson(route('webhooks.parties.soloist', $party))->assertNoContent();

    Queue::assertPushed(PartyUpdate::class, 1);
});

it('schedules overlap guards, the failed job prune and the Horizon snapshot', function () {
    $events = collect(Schedule::events());
    $find = fn (string $needle) => $events->first(fn ($e) => str_contains((string) $e->command, $needle));

    expect($find('party:fallback')->withoutOverlapping)->toBeTrue()
        ->and($find('party:force')->withoutOverlapping)->toBeTrue()
        ->and($find('queue:prune-failed --hours=168')->expression)->toBe('0 0 * * *')
        ->and($find('horizon:snapshot')->expression)->toBe('*/5 * * * *');
});

it('runs partyupdates on its own Horizon supervisor with a wait threshold', function () {
    $supervisors = config('horizon.defaults');

    expect($supervisors['supervisor-partyupdates']['queue'])->toBe(['partyupdates'])
        ->and($supervisors['supervisor-1']['queue'])->not->toContain('partyupdates')
        ->and(config('horizon.waits'))->toHaveKey('redis:partyupdates');
});
