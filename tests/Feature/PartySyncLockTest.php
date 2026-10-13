<?php

use App\Jobs\PartyUpdate;
use App\Models\Party;
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
