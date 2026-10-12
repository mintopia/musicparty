<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Theming\Actions\RenderPartyThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function contextUser(Party $party, string $role): User
{
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($party)->for($user);

    match ($role) {
        'host' => $member->host()->create(),
        'moderator' => $member->moderator()->create(),
        'banned' => $member->banned()->create(),
        default => $member->create(),
    };

    return $user;
}

dataset('party pages', [
    'show' => '/parties/ABCD',
    'queue' => '/parties/ABCD/queue',
    'search' => '/parties/ABCD/search',
    'history' => '/parties/ABCD/history',
    'party' => '/parties/ABCD/party',
    'log' => '/parties/ABCD/log',
    'members' => '/parties/ABCD/members',
    'stats' => '/parties/ABCD/stats',
    'settings' => '/parties/ABCD/settings',
    'blocklist' => '/parties/ABCD/blocklist',
    'mods' => '/parties/ABCD/mods',
    'pending' => '/parties/ABCD/requests/pending',
    'theme' => '/parties/ABCD/theme',
    'player' => '/parties/ABCD/player',
]);

it('shares the party context on every party page', function (string $path) {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'name' => 'Friday LAN', 'player_kind' => 'browser']);
    $user = contextUser($party, 'host');
    $css = app(RenderPartyThemeCss::class)->handle($party);

    $this->withoutVite()->actingAs($user)->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.party', ['code' => 'ABCD', 'name' => 'Friday LAN', 'state' => 'live'])
            ->where('partyContext.nowPlaying', null)
            ->where('partyContext.role', 'host')
            ->where('partyContext.themeCss', $css));
})->with('party pages');

it('themes the party context css', function () {
    $party = Party::factory()->live()->withTheme(['accent' => '#ff0066'])->create(['code' => 'ABCD']);
    $user = contextUser($party, 'host');
    $css = app(RenderPartyThemeCss::class)->handle($party);

    expect($css)->not->toBe('');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/members')
        ->assertInertia(fn (Assert $page): Assert => $page->where('partyContext.themeCss', $css));
});

it('derives the role from membership', function (string $role, string $expected) {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, $role);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page->where('partyContext.role', $expected));
})->with([
    'host' => ['host', 'host'],
    'moderator' => ['moderator', 'moderator'],
    'guest' => ['guest', 'member'],
    'banned' => ['banned', 'viewer'],
]);

it('builds the menu through policies', function (string $role, array $keys) {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, $role);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.menu', fn (Collection $menu): bool => $menu->pluck('key')->all() === $keys));
})->with([
    'host' => ['host', ['queue', 'search', 'history', 'stats', 'users', 'tv', 'moderation', 'pending', 'log', 'mods', 'theme', 'player-tokens', 'settings']],
    'moderator' => ['moderator', ['queue', 'search', 'history', 'stats', 'users', 'tv', 'moderation', 'pending', 'log']],
    'guest' => ['guest', ['queue', 'search', 'history', 'stats', 'tv']],
    'banned' => ['banned', []],
]);

it('builds menu hrefs and labels', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, 'guest');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.menu.0', ['key' => 'queue', 'label' => 'Queue', 'href' => '/parties/ABCD'])
            ->where('partyContext.menu.1', ['key' => 'search', 'label' => 'Search', 'href' => '/parties/ABCD/search']));
});

it('lists web player for the host only on a browser player party', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'player_kind' => 'browser']);
    $user = contextUser($party, 'host');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.menu', fn (Collection $menu): bool => $menu->pluck('key')->contains('web-player')));
});

it('links the TV mode entry to the party tv page', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, 'guest');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.menu.4', ['key' => 'tv', 'label' => 'TV Mode', 'href' => '/parties/ABCD/tv']));
});

it('shares the now playing payload with timing', function () {
    $this->freezeSecond();
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, 'host');
    TrackRequest::factory()->for($party)->fallback()->status(RequestStatus::Playing)
        ->create(['title' => 'Now', 'duration_ms' => 200000, 'started_at' => now()->subSeconds(30)]);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/members')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.nowPlaying.track.title', 'Now')
            ->where('partyContext.nowPlaying.started_at', now()->subSeconds(30)->toIso8601String())
            ->where('partyContext.nowPlaying.position_ms', 30000)
            ->where('partyContext.nowPlaying.paused', false)
            ->where('partyContext.nowPlaying.net_rating', 0));
});

it('marks now playing paused when the party is paused', function () {
    $party = Party::factory()->create(['code' => 'ABCD', 'state' => 'paused']);
    $user = contextUser($party, 'host');
    TrackRequest::factory()->for($party)->fallback()->status(RequestStatus::Playing)->create(['started_at' => now()]);

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/members')
        ->assertInertia(fn (Assert $page): Assert => $page->where('partyContext.nowPlaying.paused', true));
});

it('omits the party context outside a party', function (string $path) {
    $user = User::factory()->withRole('create-party')->create();

    $this->withoutVite()->actingAs($user)->get($path)
        ->assertInertia(fn (Assert $page): Assert => $page->missing('partyContext'));
})->with(['/', '/parties/create']);

it('omits the party context for guests on the public tv page', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);

    $this->withoutVite()->get('/parties/ABCD/tv')->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->missing('partyContext'));
});

it('keeps the party context query count constant', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $user = contextUser($party, 'host');
    TrackRequest::factory()->for($party)->fallback()->status(RequestStatus::Playing)->create();

    $count = fn (): int => countQueries(fn () => $this->withoutVite()->actingAs($user)->get('/parties/ABCD/settings')->assertOk());
    $count();
    $baseline = $count();

    TrackRequest::factory()->count(15)->for($party)->fallback()->create();
    PartyMember::factory()->count(10)->for($party)->create();

    expect($count())->toBe($baseline);
});

it('shares player capabilities for the party player', function (string $kind, bool $supported) {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'player_kind' => $kind]);
    $user = contextUser($party, 'host');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.capabilities', ['play' => $supported, 'pause' => $supported, 'skip' => $supported, 'previous' => $supported]));
})->with([
    'browser' => ['browser', false],
    'polling' => ['polling', true],
]);

it('shares no capabilities when the party has no player', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'player_kind' => null]);
    $user = contextUser($party, 'host');

    $this->withoutVite()->actingAs($user)->get('/parties/ABCD/queue')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('partyContext.capabilities', ['play' => false, 'pause' => false, 'skip' => false, 'previous' => false]));
});
