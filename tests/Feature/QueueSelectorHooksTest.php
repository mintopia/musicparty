<?php

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\QueueSelector;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->party = Party::factory()->create(['active' => true, 'weighted' => false]);
    Cache::flush();
    app()->forgetInstance(FakeMusicProvider::class);
    app(FakeMusicProvider::class)->withQueueDepth(-1);
});

function hookRequest(Party $party, string $trackId, array $attributes = []): UpcomingSong
{
    $song = Song::factory()->create(['provider_track_id' => $trackId]);

    return UpcomingSong::factory()->for($party)->create(array_merge(['song_id' => $song->id, 'queued_at' => null], $attributes));
}

function sentTrackIds(): array
{
    return array_map(fn ($track) => $track->id, app(FakeMusicProvider::class)->sentToPlayer());
}

it('filters out flagged requests and requests still on hold', function () {
    hookRequest($this->party, 'FLAGGED', ['score' => 9, 'flagged_at' => now()]);
    hookRequest($this->party, 'HELD', ['score' => 8, 'not_before' => Carbon::now()->addHour()]);
    hookRequest($this->party, 'RELEASED', ['score' => 1, 'not_before' => Carbon::now()->subMinute()]);

    app(QueueSelector::class)->sendNext($this->party);

    expect(sentTrackIds())->toBe(['RELEASED']);
});

it('adds a request with hold time and score adjustment that any feature can set', function () {
    $song = Song::factory()->create(['provider_track_id' => 'ADDED']);
    $hold = Carbon::now()->addHour()->startOfSecond();

    $request = app(QueueSelector::class)->addRequest($this->party, $song, null, ['not_before' => $hold, 'score_adjustment' => 3]);

    expect($request->user_id)->toBeNull()
        ->and($request->score)->toBe(3)
        ->and($request->not_before->equalTo($hold))->toBeTrue();

    app(QueueSelector::class)->sendNext($this->party);
    expect(sentTrackIds())->toBe([]);

    Carbon::setTestNow(Carbon::now()->addHours(2));
    app(QueueSelector::class)->sendNext($this->party);
    expect(sentTrackIds())->toBe(['ADDED']);
});

it('takes the party request lock so an add cannot interleave with another creator', function () {
    config(['musicparty.request_lock_wait_seconds' => 0]);
    $song = hookRequest($this->party, 'LOCKED')->song;
    $held = Cache::lock("add-request:{$this->party->id}", 30);
    $held->get();

    expect(fn () => app(QueueSelector::class)->addRequest($this->party, $song))->toThrow(LockTimeoutException::class);

    $held->release();
    expect(app(QueueSelector::class)->addRequest($this->party, $song)->song_id)->toBe($song->id);
});

it('lets an add nest inside the party request lock without deadlocking', function () {
    config(['musicparty.request_lock_wait_seconds' => 0]);
    $song = hookRequest($this->party, 'NESTED')->song;
    $selector = app(QueueSelector::class);

    $result = $selector->withRequestLock($this->party, fn () => $selector->addRequest($this->party, $song));

    expect($result->song_id)->toBe($song->id)
        ->and(Cache::lock("add-request:{$this->party->id}", 5)->get())->toBeTrue();
});

it('reuses a waiting request for the same song', function () {
    $existing = hookRequest($this->party, 'DUP');

    $request = app(QueueSelector::class)->addRequest($this->party, $existing->song, null, ['score_adjustment' => 2]);

    expect($request->id)->toBe($existing->id)
        ->and(UpcomingSong::count())->toBe(1)
        ->and($request->score)->toBe(2);
});
