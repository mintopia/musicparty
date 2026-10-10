<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Actions\DisableMod;
use App\Domain\Mod\Actions\EnableMod;
use App\Domain\Mod\Actions\UpdateModSettings;
use App\Domain\Mod\Data\Decoration;
use App\Domain\Mod\DecorationAccent;
use App\Domain\Mod\DecorationIcon;
use App\Domain\Mod\ModRegistry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Mods\DecoratingMod;
use Tests\Fixtures\Mods\ModFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create(['nickname' => 'Alice']);
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->queued = TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['status' => RequestStatus::Queued]);
    $this->hot = [
        'mod_id' => 'decorating',
        'badge' => 'HOT',
        'label' => null,
        'icon' => 'flame',
        'accent' => 'danger',
        'variant' => 'solid',
    ];
    Sanctum::actingAs($this->user);
});

/**
 * @return array<int, array<string, mixed>>
 */
function queueDecorations(): array
{
    return test()->getJson('/api/v1/parties/ABCD/queue')->assertOk()->json('data.0.decorations');
}

it('returns an empty decorations list when no Mod is enabled', function () {
    expect(queueDecorations())->toBe([]);
});

it('carries decorations in the queue API and the snapshot', function () {
    ModFixtures::enable($this->party, new DecoratingMod);

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect(queueDecorations())->toBe([$this->hot])
        ->and($snapshot['queue'][0]['decorations'])->toBe([$this->hot]);
});

it('carries decorations on now playing and up next in the snapshot', function () {
    $this->queued->forceFill(['status' => RequestStatus::Playing])->save();
    TrackRequest::factory()->for($this->party)->for($this->member, 'requester')->create(['status' => RequestStatus::UpNext]);
    ModFixtures::enable($this->party, new DecoratingMod);

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['now_playing']['decorations'])->toBe([$this->hot])
        ->and($snapshot['up_next']['decorations'])->toBe([$this->hot]);
});

it('accepts Decoration objects and defaults accent and variant', function () {
    ModFixtures::enable($this->party, new DecoratingMod('typed', [new Decoration('spoofed', null, 'Rare', DecorationIcon::Star, DecorationAccent::Info)]));

    expect(queueDecorations())->toBe([[
        'mod_id' => 'typed',
        'badge' => null,
        'label' => 'Rare',
        'icon' => 'star',
        'accent' => 'info',
        'variant' => 'soft',
    ]]);
});

it('discards raw HTML, CSS and script values', function (array $bad) {
    ModFixtures::enable($this->party, new DecoratingMod(decorations: [$bad, ['badge' => 'OK']]));

    $decorations = queueDecorations();

    expect($decorations)->toHaveCount(1)
        ->and($decorations[0]['badge'])->toBe('OK')
        ->and(json_encode(app(PartyQueueSnapshot::class)->build($this->party)))->not->toContain('<');
})->with([
    'html badge' => [['badge' => '<img src=x onerror=alert(1)>']],
    'script label' => [['label' => '<script>alert(1)</script>']],
    'style accent' => [['badge' => 'x', 'accent' => 'red;background:url(x)']],
    'unknown accent' => [['badge' => 'x', 'accent' => 'neon']],
    'unknown variant' => [['badge' => 'x', 'variant' => 'glitter']],
    'unknown icon' => [['badge' => 'x', 'icon' => 'skull']],
    'badge too long' => [['badge' => str_repeat('a', 25)]],
    'label too long' => [['label' => str_repeat('a', 61)]],
    'nothing to show' => [['accent' => 'success']],
]);

it('shows nothing once the Mod is disabled', function () {
    $mod = ModFixtures::enable($this->party, new DecoratingMod);
    ModFixtures::disable($this->party, $mod);

    expect(queueDecorations())->toBe([])
        ->and(app(PartyQueueSnapshot::class)->build($this->party)['queue'][0]['decorations'])->toBe([]);
});

it('only decorates parties where the Mod is enabled', function () {
    $other = Party::factory()->live()->create();
    ModFixtures::enable($other, new DecoratingMod);

    expect(queueDecorations())->toBe([])
        ->and(app(PartyQueueSnapshot::class)->build($this->party)['queue'][0]['decorations'])->toBe([]);
});

it('combines decorations from several Mods', function () {
    ModFixtures::enable($this->party, new DecoratingMod('first', [['badge' => 'A']]));
    ModFixtures::enable($this->party, new DecoratingMod('second', [['label' => 'B']]));

    expect(array_column(queueDecorations(), 'mod_id'))->toBe(['first', 'second']);
});

it('survives a throwing provider and logs it to the Party Log', function () {
    ModFixtures::enable($this->party, new DecoratingMod('broken', failing: true));
    ModFixtures::enable($this->party, new DecoratingMod('working', [['badge' => 'OK']]));

    $decorations = queueDecorations();

    expect($decorations)->toHaveCount(1)
        ->and($decorations[0]['mod_id'])->toBe('working');
    $entry = PartyLogEntry::query()->where('action', 'mod.decoration_failed')->firstOrFail();
    expect($entry->system_actor)->toBe('mod:broken')
        ->and($entry->details)->toMatchArray(['mod_id' => 'broken', 'request_id' => $this->queued->id]);
});

it('carries play decorations in the history API', function () {
    $play = Play::factory()->for($this->party)->create();
    ModFixtures::enable($this->party, new DecoratingMod);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.0.id', $play->id)
        ->assertJsonPath('data.0.decorations', [$this->hot]);
});

it('returns no play decorations once disabled', function () {
    Play::factory()->for($this->party)->create();

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()->assertJsonPath('data.0.decorations', []);
});

it('exposes decorations and no member identifiers on the anonymous TV page', function () {
    $this->queued->forceFill(['status' => RequestStatus::Playing])->save();
    ModFixtures::enable($this->party, new DecoratingMod);
    app('auth')->forgetGuards();

    $this->withoutVite()->get('/parties/ABCD/tv')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('nowPlaying.decorations', [$this->hot])
        ->where('enabled_mods', ['decorating'])
        ->missing('nowPlaying.party_member_id')
        ->missing('nowPlaying.requester'));
});

it('lists the enabled Mod ids on the Party pages', function () {
    ModFixtures::enable($this->party, new DecoratingMod);

    $this->withoutVite()->get('/parties/ABCD')->assertInertia(fn (Assert $page) => $page->where('enabled_mods', ['decorating']));
});

it('rebroadcasts the queue when a Mod is enabled, reconfigured or disabled', function (string $action) {
    $host = User::factory()->create();
    $this->party->forceFill(['user_id' => $host->id])->save();
    app(ModRegistry::class)->register(new DecoratingMod('broadcast-mod'));

    if ($action !== 'enable') {
        app(EnableMod::class)($host, $this->party, 'broadcast-mod');
        Cache::flush();
    }

    Queue::fake();

    match ($action) {
        'enable' => app(EnableMod::class)($host, $this->party, 'broadcast-mod'),
        'update' => app(UpdateModSettings::class)($host, $this->party, 'broadcast-mod', ['failure_behaviour' => 'hold']),
        'disable' => app(DisableMod::class)($host, $this->party, 'broadcast-mod'),
    };

    Queue::assertPushed(BroadcastPartyQueue::class, 1);
})->with(['enable', 'update', 'disable']);

it('does not rebroadcast when disabling a Mod that is not enabled', function () {
    Queue::fake();
    app(ModRegistry::class)->register(new DecoratingMod('idle'));

    app(DisableMod::class)($this->user, $this->party, 'idle');

    Queue::assertNotPushed(BroadcastPartyQueue::class);
});
