<?php

use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

beforeEach(function () {
    Cache::flush();
    $this->fake = FakeSpotify::install();
    $this->party = Party::factory()->create(['active' => true, 'poll' => true, 'backup_playlist_id' => 'BACKUP']);
    $this->party->forceFill(['last_updated_at' => now()->subMinute()])->saveQuietly();
    $this->fake->calls = [];
});

it('skips the sync when another caller holds the per-Party lock', function () {
    $lock = Cache::lock("party-sync:{$this->party->id}", 30);
    expect($lock->get())->toBeTrue();

    expect($this->party->tryUpdateState())->toBeFalse()
        ->and($this->fake->callsTo('getMyCurrentPlaybackInfo'))->toBe([]);

    $lock->release();
    expect($this->party->tryUpdateState())->toBeTrue()
        ->and($this->fake->callsTo('getMyCurrentPlaybackInfo'))->not->toBe([]);
});

it('runs the sync when overlap prevention is disabled', function () {
    config(['musicparty.prevent_overlapping_updates' => false]);
    Cache::lock("party-sync:{$this->party->id}", 30)->get();

    expect($this->party->tryUpdateState())->toBeTrue();
});

it('releases the lock after a sync', function () {
    $this->party->updateState();

    expect(Cache::lock("party-sync:{$this->party->id}", 30)->get())->toBeTrue();
});

it('releases a queued job back onto the queue while the lock is held', function () {
    Cache::lock("party-sync:{$this->party->id}", 30)->get();

    $job = new PartyUpdate($this->party);
    $queueJob = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $queueJob->shouldReceive('release')->once()->with(2);
    $job->setJob($queueJob);
    $job->handle();

    expect($this->fake->callsTo('getMyCurrentPlaybackInfo'))->toBe([]);
});

it('records the attempt time and releases the lock when a sync throws', function () {
    app()->bind(SpotifyWebAPI::class, fn () => new class extends FakeSpotify
    {
        public function getMyQueue()
        {
            throw new RuntimeException('boom');
        }
    });
    $before = $this->party->last_updated_at;
    $party = Party::findOrFail($this->party->id);

    expect(fn () => $party->tryUpdateState())->toThrow(RuntimeException::class);

    expect($this->party->fresh()->last_updated_at->gt($before))->toBeTrue()
        ->and(Cache::lock("party-sync:{$this->party->id}", 30)->get())->toBeTrue();
});

it('sends exactly one track when a concurrent sync races the Send to Player', function () {
    $user = $this->party->user;
    $song = Song::fromSpotify(FakeSpotify::track('raced'));
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($this->party);
    $upcoming->song()->associate($song);
    $upcoming->score = 5;
    $upcoming->save();

    $racer = Party::findOrFail($this->party->id);
    $racerResults = [];
    $fake = new class($racer, $racerResults) extends FakeMusicProvider
    {
        public function __construct(private Party $racer, public array &$racerResults)
        {
            parent::__construct();
        }

        public function sendToPlayer(Track $track): void
        {
            $this->racerResults[] = $this->racer->tryUpdateState();
            parent::sendToPlayer($track);
        }
    };
    app()->forgetInstance(FakeMusicProvider::class);
    app()->scoped(FakeMusicProvider::class, fn () => $fake);

    expect(Party::findOrFail($this->party->id)->tryUpdateState())->toBeTrue();

    expect($fake->callsTo('sendToPlayer'))->toHaveCount(1)
        ->and($racerResults)->toBe([false])
        ->and($upcoming->fresh()->queued_at)->not->toBeNull();

    Party::findOrFail($this->party->id)->forceFill(['last_updated_at' => now()->subMinute()])->saveQuietly();
    expect(Party::findOrFail($this->party->id)->tryUpdateState())->toBeTrue()
        ->and($fake->callsTo('sendToPlayer'))->toHaveCount(1);
});

it('declares bounded retries with exponential backoff', function () {
    $job = new PartyUpdate($this->party);

    expect($job->maxExceptions)->toBeGreaterThan(1)
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp())
        ->and($job->backoff())->toBe([2, 5, 15, 30])
        ->and(config('horizon.defaults.supervisor-1.tries'))->toBeGreaterThan(1);
});

it('succeeds on retry after a transient failure because the lock was released', function () {
    $fail = true;
    app()->bind(SpotifyWebAPI::class, function () use (&$fail) {
        return new class($fail) extends FakeSpotify
        {
            public function __construct(public bool &$fail) {}

            public function getMyQueue()
            {
                if ($this->fail) {
                    throw new RuntimeException('transient');
                }

                return parent::getMyQueue();
            }
        };
    });

    expect(fn () => (new PartyUpdate(Party::findOrFail($this->party->id)))->handle())
        ->toThrow(RuntimeException::class);

    $fail = false;
    $this->party->forceFill(['last_updated_at' => now()->subMinute()])->saveQuietly();
    (new PartyUpdate(Party::findOrFail($this->party->id)))->handle();

    expect(Cache::lock("party-sync:{$this->party->id}", 30)->get())->toBeTrue();
});

it('releases a retry back to the queue while a sync holds the lock', function () {
    $lock = Cache::lock("party-sync:{$this->party->id}", 30);
    $lock->get();

    $queueJob = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $queueJob->shouldReceive('release')->once()->with(2);
    $job = (new PartyUpdate($this->party))->setJob($queueJob);
    $job->handle();

    expect($this->fake->callsTo('getMyCurrentPlaybackInfo'))->toBe([]);
});
