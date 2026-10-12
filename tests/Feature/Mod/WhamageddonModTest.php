<?php

use App\Domain\Identity\Models\User;
use App\Domain\Mod\Actions\DisableMod;
use App\Domain\Mod\Actions\EnableMod;
use App\Domain\Mod\Actions\ResolveDecorations;
use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Mod\Whamageddon\Models\WhamageddonState;
use App\Domain\Mod\Whamageddon\WhamageddonMod;
use App\Domain\Mod\Whamageddon\WhamageddonSettings;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\RankQueue;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\BlocklistMatchType;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function whamAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-12-01 {$time}", config('app.timezone'));
}

function whamRunHourly(Party $party): int
{
    Cache::flush();

    return app(RunScheduledActions::class)($party);
}

function whamScore(Party $party, TrackRequest $request): int
{
    $ranked = app(RankQueue::class)($party, TrackRequest::query()->where('party_id', $party->id)->where('status', RequestStatus::Queued));

    return (int) $ranked->requests->firstWhere('id', $request->id)?->score;
}

/**
 * @param  array<string, mixed>  $settings
 */
function whamEnable(Party $party, array $settings = []): void
{
    PartyMod::factory()->for($party)->forMod(WhamageddonMod::ID)->disabled()->create(['settings' => ['track' => 'track-3', ...$settings]]);
    app(EnableMod::class)(User::factory()->create(), $party, WhamageddonMod::ID);
}

function whamRequest(Party $party): TrackRequest
{
    return TrackRequest::query()->where('party_id', $party->id)->where('provider_track_id', 'track-3')->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->travelTo(whamAt('12:00'));
    Queue::fake();
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create();
});

function whamNotBefore(TrackRequest $request): string
{
    $notBefore = whamFresh($request)->not_before;
    assert($notBefore !== null);

    return $notBefore->toDateTimeString();
}

function whamFresh(TrackRequest $request): TrackRequest
{
    return TrackRequest::query()->findOrFail($request->id);
}

it('parses a Spotify link or id and defaults to the Wham! track', function (array $values, string $expected) {
    expect(new WhamageddonSettings($values)->trackId())->toBe($expected);
})->with([
    'default' => [[], WhamageddonSettings::DEFAULT_TRACK],
    'link' => [['track' => 'https://open.spotify.com/track/2FRnf9qhLbvw8fu4IBXx78?si=abc'], '2FRnf9qhLbvw8fu4IBXx78'],
    'uri' => [['track' => 'spotify:track:2FRnf9qhLbvw8fu4IBXx78'], '2FRnf9qhLbvw8fu4IBXx78'],
    'id' => [['track' => 'abc123'], 'abc123'],
]);

it('queues a decorated system Request when enabled and names the Mod in the Party Log', function () {
    whamEnable($this->party);

    $request = whamRequest($this->party);
    expect($request->party_member_id)->toBeNull()
        ->and($request->status)->toBe(RequestStatus::Queued)
        ->and(WhamageddonState::query()->sole()->request_id)->toBe($request->id)
        ->and(app(ResolveDecorations::class)($request))->toBe([[
            'mod_id' => 'whamageddon', 'badge' => 'Whamageddon', 'label' => null, 'icon' => null, 'accent' => 'danger', 'variant' => 'soft',
        ]]);

    $created = PartyLogEntry::query()->where('action', 'mod.system_request_created')->sole();
    expect($created->system_actor)->toBe('mod:whamageddon');
});

it('adds the hourly bonus outside quiet hours and logs it', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);

    whamRunHourly($this->party);
    expect(whamScore($this->party, $request))->toBe(1);

    $this->travelTo(whamAt('13:00'));
    whamRunHourly($this->party);

    expect(whamScore($this->party, $request))->toBe(2)
        ->and(PartyLogEntry::query()->where('action', 'mod.whamageddon_bonus_added')->count())->toBe(2)
        ->and(PartyLogEntry::query()->where('action', 'mod.whamageddon_bonus_added')->latest('id')->firstOrFail()->system_actor)->toBe('mod:whamageddon');
});

it('does not apply the bonus to other Requests', function () {
    whamEnable($this->party);
    whamRunHourly($this->party);
    $other = TrackRequest::factory()->create(['party_id' => $this->party->id, 'status' => RequestStatus::Queued]);

    expect(whamScore($this->party, $other))->toBe(0);
});

it('adds no bonus during quiet hours', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);
    $this->travelTo(whamAt('03:00'));

    whamRunHourly($this->party);

    expect(whamScore($this->party, $request))->toBe(0)
        ->and(PartyLogEntry::query()->where('action', 'mod.whamageddon_bonus_added')->count())->toBe(0)
        ->and(whamNotBefore($request))->toBe(whamAt('10:00')->toDateTimeString());
});

it('holds the Request from the evening until quiet hours end and releases it after', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);
    $this->travelTo(whamAt('23:30'));

    whamRunHourly($this->party);

    expect(whamNotBefore($request))->toBe(whamAt('10:00')->addDay()->toDateTimeString())
        ->and(app(SelectUpNext::class)($this->party))->toBeNull();

    $this->travelTo(whamAt('10:00')->addDay()->subMinute());
    expect(app(SelectUpNext::class)($this->party))->toBeNull();

    $this->travelTo(whamAt('10:00')->addDay());
    expect(app(SelectUpNext::class)($this->party)?->is($request))->toBeTrue();
});

it('clears the hold at the first run after quiet hours end', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);
    $this->travelTo(whamAt('23:30'));
    whamRunHourly($this->party);

    $this->travelTo(whamAt('10:05')->addDay());
    whamRunHourly($this->party);

    expect(whamFresh($request)->not_before)->toBeNull()
        ->and(PartyLogEntry::query()->where('action', 'request.not_before_set')->count())->toBe(2);
});

it('adds the Track again once the interval has passed since it played', function () {
    whamEnable($this->party);
    $first = whamRequest($this->party);
    whamRunHourly($this->party);
    $first->forceFill(['status' => RequestStatus::Played])->save();
    TrackRequest::query()->whereKey($first->id)->update(['updated_at' => whamAt('11:00')]);
    $this->travelTo(whamAt('12:30'));

    expect(whamRunHourly($this->party))->toBe(0)
        ->and(TrackRequest::query()->count())->toBe(1);

    $this->travelTo(whamAt('13:05'));

    expect(whamRunHourly($this->party))->toBe(1);

    $second = whamRequest($this->party);
    $state = WhamageddonState::query()->sole();
    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(RequestStatus::Queued)
        ->and($state->request_id)->toBe($second->id)
        ->and($state->accumulated_bonus)->toBe(0)
        ->and(whamScore($this->party, $second))->toBe(0);
});

it('removes a Queued Request when disabled and names the Mod in the Party Log', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);

    app(DisableMod::class)(User::factory()->create(), $this->party, WhamageddonMod::ID);

    $entry = PartyLogEntry::query()->where('action', 'mod.request_removed')->sole();
    expect(whamFresh($request)->status)->toBe(RequestStatus::Removed)
        ->and($entry->system_actor)->toBe('mod:whamageddon')
        ->and(WhamageddonState::query()->sole()->request_id)->toBeNull();
});

it('leaves a Request that has already played alone when disabled', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);
    $request->forceFill(['status' => RequestStatus::Played])->save();

    app(DisableMod::class)(User::factory()->create(), $this->party, WhamageddonMod::ID);

    expect(whamFresh($request)->status)->toBe(RequestStatus::Played)
        ->and(PartyLogEntry::query()->where('action', 'mod.request_removed')->count())->toBe(0);
});

it('does nothing while the Party is Paused', function () {
    whamEnable($this->party);
    $request = whamRequest($this->party);
    $this->party->forceFill(['state' => PartyState::Paused])->save();
    $this->travelTo(whamAt('23:30'));

    expect(whamRunHourly($this->party->fresh()))->toBe(0)
        ->and(whamScore($this->party, $request))->toBe(0)
        ->and(whamFresh($request)->not_before)->toBeNull();
});

it('logs a Blocklisted Track refusal once per run', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Gamma Tune')->create();
    whamEnable($this->party);

    $refusals = fn (): int => PartyLogEntry::query()->where('action', 'mod.system_request_refused')->count();
    expect($refusals())->toBe(1)
        ->and(TrackRequest::query()->count())->toBe(0);

    whamRunHourly($this->party);
    expect($refusals())->toBe(2);

    $this->travelTo(whamAt('13:05'));
    whamRunHourly($this->party);
    expect($refusals())->toBe(3);
});
