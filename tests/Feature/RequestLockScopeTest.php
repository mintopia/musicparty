<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\FallbackPlaylist;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\QueueSelector;
use App\Services\Requests\RequestLocks;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

function lockMember(Party $party): User
{
    $user = User::factory()->create(['first_login' => false]);
    $member = new PartyMember;
    $member->party()->associate($party);
    $member->user()->associate($user);
    $member->role()->associate(PartyMemberRole::query()->where('code', 'user')->first() ?? PartyMemberRole::query()->forceCreate(['code' => 'user', 'name' => 'User']));
    $member->save();

    return $user;
}

function lockPost(Party $party, User $user)
{
    Sanctum::actingAs($user);

    return test()->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), ['provider_track_id' => 't1']);
}

beforeEach(function () {
    Cache::flush();
    app()->forgetInstance(FakeMusicProvider::class);
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'allow_requests' => true]);
    $this->withoutVite();
});

it('makes no provider call while the party request lock is held', function () {
    $partyId = $this->party->id;
    $heldDuring = [];
    $probe = new class extends FakeMusicProvider
    {
        public ?Closure $onCall = null;

        protected function record(string $method, array $args): void
        {
            parent::record($method, $args);
            if ($this->onCall) {
                ($this->onCall)($method);
            }
        }
    };
    $probe->withTrack(FakeFallbackPlaylist::track('t1'));
    $probe->onCall = function (string $method) use ($partyId, &$heldDuring): void {
        $lock = Cache::lock("add-request:{$partyId}", 5);
        $free = $lock->get();
        $free && $lock->release();
        $heldDuring[$method] = ($heldDuring[$method] ?? false) || ! $free;
    };
    app()->scoped(FakeMusicProvider::class, fn () => $probe);

    lockPost($this->party, lockMember($this->party))->assertSuccessful();

    expect($heldDuring)->toHaveKey('getTrack')
        ->and(array_filter($heldDuring))->toBe([])
        ->and(UpcomingSong::count())->toBe(1);
});

it('does not return 429 for another member while a slow request is in flight', function () {
    config(['musicparty.request_lock_wait_seconds' => 0]);
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));
    $first = lockMember($this->party);
    $second = lockMember($this->party);
    $memberId = PartyMember::query()->where('user_id', $first->id)->value('id');
    $slow = Cache::lock("request-store:{$this->party->id}:{$memberId}", 30);
    $slow->get();

    lockPost($this->party, $second)->assertSuccessful();

    $slow->release();
    expect(UpcomingSong::count())->toBe(1);
});

it('creates one request for duplicate requests of the same track', function () {
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));

    lockPost($this->party, lockMember($this->party))->assertSuccessful();
    lockPost($this->party, lockMember($this->party))->assertSuccessful();

    expect(UpcomingSong::count())->toBe(1)->and(UpcomingSong::sole()->votes()->count())->toBe(2);
});

it('backfills through the locked add path without duplicating an existing request', function () {
    Queue::fake();
    $party = Party::factory()->create(['backup_playlist_id' => 'BACKUP']);
    Cache::flush();
    FakeFallbackPlaylist::seed(['A', 'B', 'C', 'D', 'E', 'F', 'G']);
    app(FallbackPlaylist::class)->refresh($party);

    config(['musicparty.request_lock_wait_seconds' => 0]);
    $held = Cache::lock("add-request:{$party->id}", 30);
    $held->get();
    expect(fn () => app(FallbackPlaylist::class)->backfill($party))->toThrow(LockTimeoutException::class)
        ->and(UpcomingSong::count())->toBe(0);
    $held->release();

    app(FallbackPlaylist::class)->backfill($party);
    app(FallbackPlaylist::class)->backfill($party);

    $ids = UpcomingSong::query()->with('song')->get()->pluck('song.provider_track_id');
    expect($ids)->toHaveCount(Party::MINIMUM_UPCOMING)->and($ids->unique()->count())->toBe($ids->count());
});

it('does not rely on a lock another owner has taken', function () {
    $locks = app(RequestLocks::class);
    $name = "add-request:{$this->party->id}";

    $locks->run($this->party, function () use ($locks, $name): void {
        expect($locks->holds($this->party))->toBeTrue();
        Cache::lock($name)->forceRelease();
        $thief = Cache::lock($name, 30);
        $thief->get();

        expect($locks->holds($this->party))->toBeFalse();
        $thief->release();
    });

    expect($locks->holds($this->party))->toBeFalse();
});

it('does not release a lock another owner took over', function () {
    $locks = app(RequestLocks::class);
    $name = "add-request:{$this->party->id}";
    $thief = Cache::lock($name, 30);

    $locks->run($this->party, function () use ($name, $thief): void {
        Cache::lock($name)->forceRelease();
        $thief->get();
    });

    expect(Cache::lock($name, 5)->get())->toBeFalse();
    $thief->release();
});

it('lets withRequestLock nest', function () {
    $selector = app(QueueSelector::class);

    $result = $selector->withRequestLock($this->party, fn () => $selector->withRequestLock($this->party, fn () => 'ok'));

    expect($result)->toBe('ok');
});
