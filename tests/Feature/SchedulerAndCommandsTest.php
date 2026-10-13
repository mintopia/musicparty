<?php

use App\Events\Cron\DayTickEvent;
use App\Events\Cron\HourTickEvent;
use App\Events\Cron\MinuteTickEvent;
use App\Jobs\PartyUpdate;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\Data\Device;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Support\FakeFallbackPlaylist;
use Tests\Support\FakeSpotify;

/**
 * @return array<string, Illuminate\Console\Scheduling\Event>
 */
function scheduledEvents(): array
{
    $events = [];
    foreach (app(Schedule::class)->events() as $event) {
        $key = $event->command !== null
            ? trim(preg_replace('/^.*artisan[\'"]?\s+/', '', $event->command))
            : $event->description;
        $events[$key] = $event;
    }

    return $events;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function activeParty(array $attributes = []): Party
{
    $party = Party::factory()->create(['active' => false]);
    $party->forceFill(array_merge(['active' => true, 'poll' => true], $attributes))->saveQuietly();

    return $party->fresh();
}

dataset('scheduled entries', [
    'sanctum prune' => ['sanctum:prune-expired --hours=24', '0 0 * * *'],
    'party fallback' => ['party:fallback', '* * * * *'],
    'party force' => ['party:force', '* * * * *'],
    'refresh tokens' => ['party:refreshaccesstokens', '* * * * *'],
    'minute tick' => ['tick:minute', '* * * * *'],
    'hour tick' => ['tick:hour', '0 * * * *'],
    'day tick' => ['tick:day', '0 0 * * *'],
]);

it('registers every scheduled entry with its frequency and onOneServer', function (string $key, string $expression) {
    $events = scheduledEvents();

    expect($events)->toHaveKey($key);
    $event = $events[$key];
    expect($event->expression)->toBe($expression)->and($event->onOneServer)->toBeTrue();
})->with('scheduled entries');

it('registers exactly the known scheduled entries', function () {
    expect(array_keys(scheduledEvents()))->toEqualCanonicalizing([
        'sanctum:prune-expired --hours=24', 'party:fallback', 'party:force',
        'party:refreshaccesstokens', 'tick:minute', 'tick:hour', 'tick:day',
    ]);
});

it('dispatches the tick event from each tick entry', function (string $key, string $eventClass) {
    Event::fake([$eventClass]);

    scheduledEvents()[$key]->run(app());

    Event::assertDispatchedTimes($eventClass, 1);
})->with([
    'minute' => ['tick:minute', MinuteTickEvent::class],
    'hour' => ['tick:hour', HourTickEvent::class],
    'day' => ['tick:day', DayTickEvent::class],
]);

it('party:fallback dispatches updates for active polling parties never updated or older than 90 seconds', function () {
    Queue::fake();
    $never = activeParty(['last_updated_at' => null]);
    $stale = activeParty(['last_updated_at' => now()->subSeconds(91)]);
    activeParty(['last_updated_at' => now()->subSeconds(30)]);
    activeParty(['active' => false, 'last_updated_at' => null]);
    activeParty(['poll' => false, 'last_updated_at' => null]);

    $this->artisan('party:fallback')->assertSuccessful();

    Queue::assertPushed(PartyUpdate::class, 2);
    $pushed = Queue::pushed(PartyUpdate::class)->map(fn (PartyUpdate $job) => new ReflectionProperty($job, 'party')->getValue($job)->id);
    expect($pushed->all())->toEqualCanonicalizing([$never->id, $stale->id]);
});

it('party:fallback dispatches nothing when no party is overdue', function () {
    Queue::fake();

    $this->artisan('party:fallback')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('party:force fails for an unknown code', function () {
    $this->artisan('party:force', ['code' => 'NOPE00'])
        ->expectsOutputToContain('Unable to find suitable party with code NOPE00')
        ->assertFailed();
});

it('party:force fails for a valid code whose party is ineligible', function () {
    $party = activeParty(['poll' => true, 'force' => true, 'device_id' => 'dev1', 'last_updated_at' => now()->subMinutes(20)]);

    $this->artisan('party:force', ['code' => $party->code])
        ->expectsOutputToContain("Unable to find suitable party with code {$party->code}")
        ->assertFailed();
});

it('party:force starts a forced party whose device is online without taking a sync lock (E5 R2)', function () {
    FakeSpotify::install();
    $fake = FakeFallbackPlaylist::seed(['bk1'], 'backup')->withDevices([new Device('dev1', 'Dev One', 'Speaker', true)]);
    $party = activeParty(['poll' => false, 'force' => true, 'device_id' => 'dev1', 'backup_playlist_id' => 'backup', 'last_updated_at' => now()->subMinutes(20)]);

    $this->artisan('party:force')
        ->expectsOutputToContain('Device is online, starting party')
        ->assertSuccessful();

    expect($party->fresh()->last_updated_at->gt(now()->subMinute()))->toBeTrue();
});

it('party:force skips a party whose device is offline', function () {
    app(FakeMusicProvider::class)->withDevices([new Device('other', 'Other', 'Speaker', true)]);
    $party = activeParty(['poll' => false, 'force' => true, 'device_id' => 'dev1', 'last_updated_at' => now()->subMinutes(20)]);
    $before = $party->last_updated_at;

    $this->artisan('party:force')->doesntExpectOutputToContain('Device is online')->assertSuccessful();

    expect($party->fresh()->last_updated_at->equalTo($before))->toBeTrue();
});

it('party:force ignores polling, unforced, inactive, deviceless and recently updated parties', function () {
    app(FakeMusicProvider::class)->withDevices([new Device('dev1', 'Dev One', 'Speaker', true)]);
    $old = now()->subMinutes(20);
    activeParty(['poll' => true, 'force' => true, 'device_id' => 'dev1', 'last_updated_at' => $old]);
    activeParty(['poll' => false, 'force' => false, 'device_id' => 'dev1', 'last_updated_at' => $old]);
    activeParty(['poll' => false, 'force' => true, 'active' => false, 'device_id' => 'dev1', 'last_updated_at' => $old]);
    activeParty(['poll' => false, 'force' => true, 'device_id' => null, 'last_updated_at' => $old]);
    activeParty(['poll' => false, 'force' => true, 'device_id' => 'dev1', 'last_updated_at' => now()->subMinutes(5)]);

    $this->artisan('party:force')->doesntExpectOutputToContain('Checking to see if we can play')->assertSuccessful();
});

it('party:force with a code processes only that party', function () {
    FakeSpotify::install();
    $fake = FakeFallbackPlaylist::seed(['bk1'], 'backup')->withDevices([new Device('dev1', 'Dev One', 'Speaker', true)]);
    $attributes = ['poll' => false, 'force' => true, 'device_id' => 'dev1', 'backup_playlist_id' => 'backup', 'last_updated_at' => now()->subMinutes(20)];
    $target = activeParty($attributes);
    $other = activeParty($attributes);

    $this->artisan('party:force', ['code' => $target->code])->assertSuccessful();

    expect($target->fresh()->last_updated_at->gt(now()->subMinute()))->toBeTrue()
        ->and($other->fresh()->last_updated_at->lt(now()->subMinutes(15)))->toBeTrue();
});

it('party:refreshaccesstokens reports when there is no spotify provider', function () {
    $this->artisan('party:refreshaccesstokens')
        ->expectsOutput('No spotify provider found')
        ->assertSuccessful();
});

it('party:refreshaccesstokens refreshes expiring spotify accounts lazily via the SpotifyGateway with no lock or result check (E5 R5, R6)', function () {
    $provider = SocialProvider::factory()->create(['code' => 'spotify']);
    $expiring = User::factory()->create();
    $fresh = User::factory()->create();
    LinkedAccount::factory()->create(['user_id' => $expiring->id, 'social_provider_id' => $provider->id, 'access_token_expires_at' => now()->addMinutes(3)]);
    LinkedAccount::factory()->create(['user_id' => $fresh->id, 'social_provider_id' => $provider->id, 'access_token_expires_at' => now()->addMinutes(10)]);

    $refreshed = [];
    $this->mock(SpotifyGateway::class, function (MockInterface $mock) use (&$refreshed) {
        $mock->shouldReceive('client')->andReturnUsing(function (User $user) use (&$refreshed) {
            $refreshed[] = $user->id;

            return null;
        });
    });

    $this->artisan('party:refreshaccesstokens')->assertSuccessful();

    expect($refreshed)->toBe([$expiring->id]);
});

it('does not register party:check (E5 R12)', function () {
    expect(array_keys(Artisan::all()))->not->toContain('party:check');
});

it('lets the party owner manage the party and not a stranger (E5 R13)', function () {
    $party = Party::factory()->create();
    $owner = $party->user;
    $stranger = User::factory()->create();

    expect($party->canBeManagedBy($owner))->toBeTrue()
        ->and($party->canBeManagedBy($stranger))->toBeFalse()
        ->and($party->canBeManagedBy(null))->toBeFalse();
});
