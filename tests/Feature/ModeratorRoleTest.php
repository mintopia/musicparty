<?php

use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->withoutVite();
    $this->fake = dashboardFake()->withQueueDepth(1);
    $this->party = dashboardParty();
    Artisan::call('mods:sync');
    app(PartyMods::class)->enable($this->party, AiRequestReviewMod::CODE);
    $this->moderator = dashboardUser($this->party, 'moderator');
    $this->vip = dashboardUser($this->party, 'vip');
    $this->regular = dashboardUser($this->party, 'user');
    $this->target = PartyMember::query()->where('user_id', $this->regular->id)->firstOrFail();
});

it('answers canBeModeratedBy for creator, owner role and moderator only', function () {
    $owner = dashboardUser($this->party, 'owner');

    expect($this->party->canBeModeratedBy($this->party->user))->toBeTrue()
        ->and($this->party->canBeModeratedBy($owner))->toBeTrue()
        ->and($this->party->canBeModeratedBy($this->moderator))->toBeTrue()
        ->and($this->party->canBeModeratedBy($this->vip))->toBeFalse()
        ->and($this->party->canBeModeratedBy($this->regular))->toBeFalse()
        ->and($this->party->canBeModeratedBy(null))->toBeFalse()
        ->and($this->party->canBeManagedBy($this->moderator))->toBeFalse();
});

it('grants the moderate ability to admins, owners and moderators only', function () {
    expect($this->moderator->can('moderate', $this->party))->toBeTrue()
        ->and($this->party->user->can('moderate', $this->party))->toBeTrue()
        ->and(dashboardUser($this->party, 'admin')->can('moderate', $this->party))->toBeTrue()
        ->and($this->vip->can('moderate', $this->party))->toBeFalse()
        ->and($this->regular->can('moderate', $this->party))->toBeFalse()
        ->and($this->moderator->can('update', $this->party))->toBeFalse()
        ->and($this->moderator->can('player', $this->party))->toBeFalse();
});

describe('pages a moderator can open', function () {
    it('opens a page', function (string $route, bool $withMember) {
        $this->actingAs($this->moderator)->get(route($route, [$this->party->code, ...($withMember ? [$this->target->id] : [])]))->assertOk();
    })->with([
        'dashboard' => ['parties.dashboard', false],
        'songs' => ['parties.songs.index', false],
        'ai reviews' => ['parties.ai-reviews.index', false],
        'rules (read-only list)' => ['parties.moderation.index', false],
        'members list' => ['parties.users.index', false],
        'member profile' => ['parties.users.show', true],
        'flagged requests' => ['parties.flagged.index', false],
    ]);

    it('forbids a vip and a plain user from the moderator pages', function (string $route) {
        foreach ([$this->vip, $this->regular] as $user) {
            $this->actingAs($user)->get(route($route, $this->party->code))->assertForbidden();
        }
    })->with(['parties.dashboard', 'parties.songs.index', 'parties.ai-reviews.index', 'parties.moderation.index', 'parties.users.index', 'parties.flagged.index']);
});

describe('pages a moderator cannot open', function () {
    it('forbids settings and write pages', function (string $route, bool $withMember) {
        $this->actingAs($this->moderator)->get(route($route, [$this->party->code, ...($withMember ? [$this->target->id] : [])]))->assertForbidden();
    })->with([
        'settings' => ['parties.edit', false],
        'mods' => ['parties.mods.edit', false],
        'create rule' => ['parties.moderation.create', false],
        'edit member' => ['parties.users.edit', true],
        'web player' => ['parties.player', false],
    ]);

    it('forbids saving settings', function () {
        $this->actingAs($this->moderator)->patch(route('parties.update', $this->party->code), ['name' => 'Hijacked'])->assertForbidden();

        expect($this->party->fresh()->name)->not->toBe('Hijacked');
    });

    it('forbids creating a rule', function () {
        $this->actingAs($this->moderator)->post(route('parties.moderation.store', $this->party->code), ['type' => 'mtId', 'value' => 'x'])->assertForbidden();
    });

    it('forbids changing a role', function () {
        $this->actingAs($this->moderator)->put(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'banned'])->assertForbidden();

        expect($this->target->fresh()->role->code)->toBe('user');
    });
});

describe('role assignment', function () {
    it('lets the owner assign the moderator role and lists it as an option', function () {
        $owner = $this->party->user;

        $this->actingAs($owner)->put(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'moderator'])->assertRedirect();
        expect($this->target->fresh()->role->code)->toBe('moderator');

        $this->actingAs($owner)->get(route('parties.users.edit', [$this->party->code, $this->target->id]))
            ->assertInertia(fn (Assert $page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('code')->contains('moderator')));
    });

    it('stops a moderator promoting themselves', function () {
        $self = PartyMember::query()->where('user_id', $this->moderator->id)->firstOrFail();

        $this->actingAs($this->moderator)->put(route('parties.users.update', [$this->party->code, $self->id]), ['role' => 'owner'])->assertForbidden();

        expect($self->fresh()->role->code)->toBe('moderator');
    });
});

describe('request moderation', function () {
    it('lets a moderator schedule a request', function () {
        $request = dashboardRequest($this->party);

        $this->actingAs($this->moderator)->patch(route('parties.songs.update', [$this->party->code, $request->id]), ['scheduled_for' => '2030-01-01T20:00'])->assertRedirect();

        expect($request->fresh()->scheduled_for)->not->toBeNull();
    });

    it('forbids a vip scheduling a request', function () {
        $request = dashboardRequest($this->party);

        $this->actingAs($this->vip)->patch(route('parties.songs.update', [$this->party->code, $request->id]), ['scheduled_for' => '2030-01-01T20:00'])->assertForbidden();
    });

    it('lets a moderator remove a request', function () {
        $request = dashboardRequest($this->party);
        Sanctum::actingAs($this->moderator);

        $this->deleteJson(route('api.v1.parties.upcomingsongs.destroy', [$this->party, $request]))->assertSuccessful();

        expect(UpcomingSong::find($request->id))->toBeNull();
    });

    it('forbids a vip removing a request', function () {
        $request = dashboardRequest($this->party);
        Sanctum::actingAs($this->vip);

        $this->deleteJson(route('api.v1.parties.upcomingsongs.destroy', [$this->party, $request]))->assertForbidden();
    });

    it('lets a moderator approve and reject flagged requests', function () {
        $approve = dashboardRequest($this->party, ['flagged_at' => now(), 'user_id' => $this->regular->id]);
        $reject = dashboardRequest($this->party, ['flagged_at' => now(), 'user_id' => $this->regular->id]);

        $this->actingAs($this->moderator)->post(route('parties.flagged.approve', [$this->party->code, $approve->id]))->assertRedirect();
        $this->actingAs($this->moderator)->post(route('parties.flagged.reject', [$this->party->code, $reject->id]))->assertRedirect();

        expect($approve->fresh()->flagged_at)->toBeNull()
            ->and(UpcomingSong::find($reject->id))->toBeNull();
    });

    it('forbids a vip approving or rejecting flagged requests', function () {
        $flagged = dashboardRequest($this->party, ['flagged_at' => now()]);

        $this->actingAs($this->vip)->post(route('parties.flagged.approve', [$this->party->code, $flagged->id]))->assertForbidden();
        $this->actingAs($this->vip)->post(route('parties.flagged.reject', [$this->party->code, $flagged->id]))->assertForbidden();
    });
});

describe('playback', function () {
    it('lets a moderator use transport controls', function () {
        Sanctum::actingAs($this->moderator);

        $this->postJson(route('api.v1.parties.control', $this->party), ['action' => 'pause']);
        expect($this->postJson(route('api.v1.parties.control', $this->party), ['action' => 'pause'])->status())->not->toBe(403);
    });

    it('lets a moderator play a request now', function () {
        $request = dashboardRequest($this->party);
        Sanctum::actingAs($this->moderator);

        expect($this->postJson(route('api.v1.parties.upcomingsongs.play', [$this->party, $request]))->status())->not->toBe(403);
    });

    it('forbids a vip using transport controls', function () {
        Sanctum::actingAs($this->vip);

        $this->postJson(route('api.v1.parties.control', $this->party), ['action' => 'pause'])->assertForbidden();
    });

    it('keeps starting playback, device listing and activation owner-only', function (string $method, string $route) {
        Sanctum::actingAs($this->moderator);

        $this->{$method}(route($route, $this->party))->assertForbidden();
    })->with([
        'start' => ['postJson', 'api.v1.parties.playback.start'],
        'devices' => ['getJson', 'api.v1.parties.devices'],
        'activate' => ['postJson', 'api.v1.parties.activate'],
    ]);
});

describe('shared props', function () {
    it('exposes canModerate without canManage to a moderator on the dashboard', function () {
        $this->actingAs($this->moderator)->get(route('parties.dashboard', $this->party->code))
            ->assertInertia(fn (Assert $page) => $page
                ->where('party.canModerate', true)
                ->where('party.canManage', false)
                ->where('party.ownerName', $this->party->user->nickname)
                ->where('can.moderate', true)
                ->where('can.player', false));
    });

    it('shares canModerate on the party page and the sidebar party list', function () {
        $this->actingAs($this->moderator)->get(route('parties.show', $this->party->code))
            ->assertInertia(fn (Assert $page) => $page
                ->where('party.canModerate', true)
                ->where('party.canManage', false)
                ->where('auth.parties.0.canModerate', true));
    });

    it('reports canModerate false for a plain member', function () {
        $this->actingAs($this->regular)->get(route('parties.show', $this->party->code))
            ->assertInertia(fn (Assert $page) => $page->where('party.canModerate', false));
    });
});

it('exposes ownerName and member roleCode on the members and moderation pages', function () {
    $nickname = $this->party->user->nickname;

    $this->actingAs($this->moderator)->get(route('parties.users.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page
            ->where('party.ownerName', $nickname)
            ->where('members.data', fn ($members) => collect($members)->every(fn ($member) => isset($member['roleCode']))));
    $this->actingAs($this->moderator)->get(route('parties.users.show', [$this->party->code, $this->target->id]))
        ->assertInertia(fn (Assert $page) => $page->where('party.ownerName', $nickname)->where('member.roleCode', 'user'));
    $this->actingAs($this->moderator)->get(route('parties.moderation.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('party.ownerName', $nickname));
});

it('authorises the managers broadcast channel for moderators only', function () {
    $channel = fn (User $user): mixed => Broadcast::driver()->getChannels()->get('party.{party}.managers');
    expect($channel($this->moderator))->not->toBeNull();

    $callback = $channel($this->moderator);
    expect($callback($this->moderator, $this->party))->toBeTrue()
        ->and($callback($this->regular, $this->party))->toBeFalse();
});
