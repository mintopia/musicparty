<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\ListPartyMembers;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
});

function seatMember(Party $party, string $role, bool $banned = false): PartyMember
{
    $member = PartyMember::factory()->for($party);
    $member = $role === 'guest' ? $member : $member->{$role}();

    return ($banned ? $member->banned() : $member)->create();
}

function operationUrl(string $operation, PartyMember $target, string $prefix = '/api/v1'): string
{
    $suffix = str_starts_with($operation, 'role:') ? 'role' : 'ban';

    return "{$prefix}/parties/ABCD/members/{$target->id}/{$suffix}";
}

/**
 * @return TestResponse<Response>
 */
function callOperation(PartyMember $actor, string $operation, PartyMember $target): TestResponse
{
    Sanctum::actingAs($actor->user()->firstOrFail());

    return match (true) {
        str_starts_with($operation, 'role:') => test()->putJson(operationUrl($operation, $target), ['role' => substr($operation, 5)]),
        $operation === 'ban' => test()->putJson(operationUrl($operation, $target)),
        default => test()->deleteJson(operationUrl($operation, $target)),
    };
}

dataset('permission matrix', [
    'host bans moderator' => ['host', false, 'moderator', false, 'ban', 200],
    'host bans vip' => ['host', false, 'vip', false, 'ban', 200],
    'host bans guest' => ['host', false, 'guest', false, 'ban', 200],
    'host cannot ban host' => ['host', false, 'host', false, 'ban', 403],
    'moderator bans vip' => ['moderator', false, 'vip', false, 'ban', 200],
    'moderator bans guest' => ['moderator', false, 'guest', false, 'ban', 200],
    'moderator cannot ban moderator' => ['moderator', false, 'moderator', false, 'ban', 403],
    'moderator cannot ban host' => ['moderator', false, 'host', false, 'ban', 403],
    'vip cannot ban' => ['vip', false, 'guest', false, 'ban', 403],
    'guest cannot ban' => ['guest', false, 'guest', false, 'ban', 403],
    'banned host cannot ban' => ['host', true, 'guest', false, 'ban', 403],
    'banned moderator cannot ban' => ['moderator', true, 'guest', false, 'ban', 403],
    'host unbans guest' => ['host', false, 'guest', true, 'unban', 200],
    'host unbans moderator' => ['host', false, 'moderator', true, 'unban', 200],
    'moderator unbans vip' => ['moderator', false, 'vip', true, 'unban', 200],
    'moderator cannot unban moderator' => ['moderator', false, 'moderator', true, 'unban', 403],
    'vip cannot unban' => ['vip', false, 'guest', true, 'unban', 403],
    'guest cannot unban' => ['guest', false, 'guest', true, 'unban', 403],
    'banned moderator cannot unban' => ['moderator', true, 'guest', true, 'unban', 403],
    'host promotes guest to moderator' => ['host', false, 'guest', false, 'role:moderator', 200],
    'host promotes guest to vip' => ['host', false, 'guest', false, 'role:vip', 200],
    'host demotes moderator to guest' => ['host', false, 'moderator', false, 'role:guest', 200],
    'host demotes vip to guest' => ['host', false, 'vip', false, 'role:guest', 200],
    'host cannot make another host' => ['host', false, 'guest', false, 'role:host', 422],
    'host role cannot be changed' => ['host', false, 'host', false, 'role:guest', 422],
    'moderator cannot change roles' => ['moderator', false, 'guest', false, 'role:vip', 403],
    'vip cannot change roles' => ['vip', false, 'guest', false, 'role:vip', 403],
    'guest cannot change roles' => ['guest', false, 'guest', false, 'role:moderator', 403],
    'banned host cannot change roles' => ['host', true, 'guest', false, 'role:vip', 403],
    'invalid role is rejected' => ['host', false, 'guest', false, 'role:overlord', 422],
]);

it('enforces the permission matrix', function (string $actorRole, bool $actorBanned, string $targetRole, bool $targetBanned, string $operation, int $status) {
    $actor = seatMember($this->party, $actorRole, $actorBanned);
    $target = $actorRole === $targetRole && $targetRole === 'host' ? $actor : seatMember($this->party, $targetRole, $targetBanned);
    $before = $target->only(['role', 'banned']);

    callOperation($actor, $operation, $target)->assertStatus($status);

    $after = $target->fresh()->only(['role', 'banned']);
    if ($status === 200) {
        expect($after)->not->toEqual($before);
    } else {
        expect($after)->toEqual($before)->and(PartyLogEntry::query()->count())->toBe(0);
    }
})->with('permission matrix');

it('keeps exactly one host when the host role is requested through the API or web', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');
    $moderator = seatMember($this->party, 'moderator');

    foreach ([$guest, $moderator] as $target) {
        callOperation($host, 'role:host', $target)->assertUnprocessable()->assertJsonValidationErrors('member');
        $this->actingAs($host->user)->put("/parties/ABCD/members/{$target->id}/role", ['role' => 'host'])
            ->assertSessionHasErrors('member');
    }

    expect($this->party->members()->where('role', PartyRole::Host)->pluck('id')->all())->toBe([$host->id])
        ->and($guest->refresh()->role)->toBe(PartyRole::Guest)
        ->and($moderator->refresh()->role)->toBe(PartyRole::Moderator)
        ->and(PartyLogEntry::query()->count())->toBe(0);
});

it('lets the host ban a moderator while retaining their role and restores abilities on unban', function () {
    $host = seatMember($this->party, 'host');
    $moderator = seatMember($this->party, 'moderator');

    callOperation($host, 'ban', $moderator)->assertOk()->assertJsonPath('data.banned', true)->assertJsonPath('data.role', 'moderator');
    callOperation($moderator, 'ban', seatMember($this->party, 'guest'))->assertForbidden();

    callOperation($host, 'unban', $moderator)->assertOk()->assertJsonPath('data.banned', false);
    callOperation($moderator, 'ban', seatMember($this->party, 'guest'))->assertOk();
});

it('refuses a moderator banning themselves with a validation error', function () {
    $moderator = seatMember($this->party, 'moderator');

    callOperation($moderator, 'ban', $moderator)->assertUnprocessable()->assertJsonValidationErrors('member');
});

it('is idempotent and logs nothing for no-op changes', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');
    $banned = seatMember($this->party, 'guest', true);

    callOperation($host, 'role:guest', $guest)->assertOk();
    callOperation($host, 'unban', $guest)->assertOk();
    callOperation($host, 'ban', $banned)->assertOk();

    expect(PartyLogEntry::query()->count())->toBe(0);
});

it('writes Party Log entries for role changes, bans and unbans', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    callOperation($host, 'role:vip', $guest)->assertOk();
    callOperation($host, 'ban', $guest)->assertOk();
    callOperation($host, 'unban', $guest)->assertOk();

    $entries = PartyLogEntry::query()->orderBy('id')->get();
    expect($entries->pluck('action')->all())->toBe(['member.role_changed', 'member.banned', 'member.unbanned'])
        ->and($entries->pluck('user_id')->unique()->all())->toBe([$host->user_id])
        ->and($entries->pluck('subject')->unique()->all())->toBe([$guest->user->nickname])
        ->and($entries[0]->details)->toMatchArray(['old' => 'guest', 'new' => 'vip', 'user_id' => $guest->user_id])
        ->and($entries[1]->details)->toMatchArray(['role' => 'vip']);
});

it('returns 404 for a member of another party', function () {
    $host = seatMember($this->party, 'host');
    $stranger = PartyMember::factory()->for(Party::factory()->create(['code' => 'WXYZ']))->create();

    callOperation($host, 'ban', $stranger)->assertNotFound();
    callOperation($host, 'role:vip', $stranger)->assertNotFound();
    callOperation($host, 'unban', $stranger)->assertNotFound();
});

it('refuses non-members and unauthenticated callers', function () {
    $guest = seatMember($this->party, 'guest');

    $this->putJson(operationUrl('ban', $guest))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->putJson(operationUrl('ban', $guest))->assertForbidden();
    $this->getJson('/api/v1/parties/ABCD/members')->assertForbidden();
});

it('lists members for moderators with joined time and refuses plain members', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');
    $banned = seatMember($this->party, 'guest', true);
    $moderator = seatMember($this->party, 'moderator');

    Sanctum::actingAs($host->user()->firstOrFail());
    $this->getJson('/api/v1/parties/ABCD/members')->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.role', 'host')
        ->assertJsonPath('data.0.is_you', true)
        ->assertJsonPath('meta.total', 4)
        ->assertJsonStructure(['data' => [['id', 'nickname', 'avatar', 'role', 'banned', 'is_you', 'joined_at']]]);

    Sanctum::actingAs($moderator->user()->firstOrFail());
    $this->getJson('/api/v1/parties/ABCD/members')->assertOk()->assertJsonCount(4, 'data');

    Sanctum::actingAs($guest->user()->firstOrFail());
    $this->getJson('/api/v1/parties/ABCD/members')->assertForbidden();

    Sanctum::actingAs($banned->user()->firstOrFail());
    $this->getJson('/api/v1/parties/ABCD/members')->assertForbidden();
});

/**
 * @return TestResponse<Response>
 */
function membersList(string $query = ''): TestResponse
{
    return test()->getJson('/api/v1/parties/ABCD/members'.$query);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function seatNamed(Party $party, string $nickname, string $role = 'guest', array $attributes = []): PartyMember
{
    $member = PartyMember::factory()->for($party)->for(User::factory()->create(['nickname' => $nickname]));

    return ($role === 'guest' ? $member : $member->{$role}())->create($attributes);
}

/**
 * @param  TestResponse<Response>  $response
 * @return list<string>
 */
function nicknames(TestResponse $response): array
{
    return array_column($response->json('data'), 'nickname');
}

describe('members list filters, sort and paging', function () {
    beforeEach(function () {
        $this->host = seatNamed($this->party, 'Hostess', 'host', ['created_at' => now()->subDays(5)]);
        $this->moderator = seatNamed($this->party, 'Bravo', 'moderator', ['created_at' => now()->subDays(1)]);
        $this->vip = seatNamed($this->party, 'Charlie', 'vip', ['created_at' => now()->subDays(4)]);
        $this->alpha = seatNamed($this->party, 'Alpha', 'guest', ['created_at' => now()->subDays(3)]);
        $this->delta = seatNamed($this->party, 'Delta_x', 'guest', ['created_at' => now()->subDays(2)]);
        $this->banned = seatNamed($this->party, 'Zulu', 'guest', ['banned' => true, 'created_at' => now()->subDays(6)]);
        Sanctum::actingAs($this->host->user()->firstOrFail());
    });

    it('defaults to role order then nickname', function () {
        expect(nicknames(membersList()))->toBe(['Hostess', 'Bravo', 'Charlie', 'Alpha', 'Delta_x', 'Zulu']);
    });

    it('searches nicknames by substring and treats wildcards literally', function () {
        expect(nicknames(membersList('?search=ALP')))->toBe(['Alpha'])
            ->and(nicknames(membersList('?search=D_lta')))->toBe([])
            ->and(nicknames(membersList('?search=_x')))->toBe(['Delta_x'])
            ->and(nicknames(membersList('?search=%')))->toBe([]);
    });

    it('filters by role', function (string $role, array $expected) {
        expect(nicknames(membersList("?role={$role}")))->toBe($expected);
    })->with([
        'host' => ['host', ['Hostess']],
        'moderator' => ['moderator', ['Bravo']],
        'vip' => ['vip', ['Charlie']],
        'guest' => ['guest', ['Alpha', 'Delta_x', 'Zulu']],
    ]);

    it('combines search and role', function () {
        expect(nicknames(membersList('?role=guest&search=lta')))->toBe(['Delta_x']);
    });

    it('sorts by nickname', function (string $direction, array $expected) {
        expect(nicknames(membersList("?sort=nickname&direction={$direction}")))->toBe($expected);
    })->with([
        'asc' => ['asc', ['Alpha', 'Bravo', 'Charlie', 'Delta_x', 'Hostess', 'Zulu']],
        'desc' => ['desc', ['Zulu', 'Hostess', 'Delta_x', 'Charlie', 'Bravo', 'Alpha']],
    ]);

    it('sorts by role rank', function (string $direction, array $expected) {
        expect(nicknames(membersList("?sort=role&direction={$direction}")))->toBe($expected);
    })->with([
        'asc' => ['asc', ['Hostess', 'Bravo', 'Charlie', 'Alpha', 'Delta_x', 'Zulu']],
        'desc' => ['desc', ['Alpha', 'Delta_x', 'Zulu', 'Charlie', 'Bravo', 'Hostess']],
    ]);

    it('sorts by joined time', function (string $direction, array $expected) {
        expect(nicknames(membersList("?sort=joined&direction={$direction}")))->toBe($expected);
    })->with([
        'asc' => ['asc', ['Zulu', 'Hostess', 'Charlie', 'Alpha', 'Delta_x', 'Bravo']],
        'desc' => ['desc', ['Bravo', 'Delta_x', 'Alpha', 'Charlie', 'Hostess', 'Zulu']],
    ]);

    it('pages results', function () {
        $first = membersList('?per_page=4&sort=nickname');
        $first->assertOk()->assertJsonPath('meta.total', 6)->assertJsonPath('meta.per_page', 4)->assertJsonPath('meta.last_page', 2);
        expect(nicknames($first))->toBe(['Alpha', 'Bravo', 'Charlie', 'Delta_x'])
            ->and(nicknames(membersList('?per_page=4&sort=nickname&page=2')))->toBe(['Hostess', 'Zulu']);
    });

    it('defaults to 25 per page', function () {
        membersList()->assertJsonPath('meta.per_page', 25);
    });

    it('breaks ties by nickname', function () {
        $at = now()->subDays(7);
        seatNamed($this->party, 'Foxtrot', 'guest', ['created_at' => $at]);
        seatNamed($this->party, 'Echo', 'guest', ['created_at' => $at]);

        expect(nicknames(membersList('?sort=joined&per_page=2')))->toBe(['Echo', 'Foxtrot'])
            ->and(nicknames(membersList('?sort=joined&search=o&role=guest')))->toBe(['Echo', 'Foxtrot']);
    });

    it('hides banned members unless banned are included', function () {
        $list = fn (bool $includeBanned): array => app(ListPartyMembers::class)($this->party, $includeBanned)->pluck('id')->all();

        expect($list(false))->not->toContain($this->banned->id)
            ->and($list(true))->toContain($this->banned->id);
    });

    it('reports the joined time as ISO', function () {
        membersList('?search=Alpha')->assertJsonPath('data.0.joined_at', $this->alpha->fresh()->created_at->toJSON());
    });

    it('rejects invalid parameters', function (string $query, string $field) {
        membersList($query)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'role' => ['?role=king', 'role'],
        'sort' => ['?sort=banned', 'sort'],
        'direction' => ['?direction=sideways', 'direction'],
        'per_page low' => ['?per_page=0', 'per_page'],
        'per_page high' => ['?per_page=101', 'per_page'],
        'per_page text' => ['?per_page=lots', 'per_page'],
        'page' => ['?page=0', 'page'],
        'search long' => ['?search='.str_repeat('a', 101), 'search'],
    ]);
});

it('renders the Members page with the props contract', function () {
    $host = seatMember($this->party, 'host');
    $banned = seatMember($this->party, 'guest', true);

    $this->actingAs($host->user)->get('/parties/ABCD/members?search=&role=guest&sort=joined&direction=desc')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Party/Members')
        ->where('party', ['code' => 'ABCD', 'name' => $this->party->name])
        ->has('members.data', 1)
        ->has('members.meta.total')
        ->has('members.links')
        ->where('members.data.0.banned', true)
        ->where('members.data.0.id', $banned->id)
        ->where('members.data.0.isYou', false)
        ->has('members.data.0.joinedAt')
        ->where('filters', ['search' => null, 'role' => 'guest', 'sort' => 'joined', 'direction' => 'desc'])
        ->where('abilities', ['canChangeRoles' => true, 'canBan' => true]));
});

it('defaults the Members page filters', function () {
    $host = seatMember($this->party, 'host');

    $this->actingAs($host->user)->get('/parties/ABCD/members')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('members.data', 1)
        ->where('members.data.0.isYou', true)
        ->where('filters', ['search' => null, 'role' => null, 'sort' => 'role', 'direction' => 'asc']));
});

it('rejects invalid Members page parameters', function () {
    $host = seatMember($this->party, 'host');

    $this->actingAs($host->user)->get('/parties/ABCD/members?sort=bogus')->assertSessionHasErrors('sort');
});

it('gives moderators their abilities and refuses vips and guests on the Members page', function (string $role, bool $allowed) {
    $member = seatMember($this->party, $role);

    $response = $this->actingAs($member->user)->get('/parties/ABCD/members');
    $allowed
        ? $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('abilities', ['canChangeRoles' => false, 'canBan' => true]))
        : $response->assertForbidden();
})->with([
    'moderator' => ['moderator', true],
    'vip' => ['vip', false],
    'guest' => ['guest', false],
]);

it('refuses the Members page to banned members and non-members', function () {
    $banned = seatMember($this->party, 'guest', true);

    $this->actingAs($banned->user)->get('/parties/ABCD/members')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/parties/ABCD/members')->assertForbidden();
});

it('redirects guests to log in for the Members page', function () {
    $this->get('/parties/ABCD/members')->assertRedirect();
});

it('changes roles and bans through the web routes with the same outcome as the API', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    $this->actingAs($host->user)->from('/parties/ABCD/members')
        ->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'moderator'])
        ->assertRedirect('/parties/ABCD/members')->assertSessionHas('success');
    expect($guest->refresh()->role)->toBe(PartyRole::Moderator);

    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/ban")->assertRedirect()->assertSessionHas('success');
    expect($guest->fresh()->banned)->toBeTrue();

    $this->actingAs($host->user)->delete("/parties/ABCD/members/{$guest->id}/ban")->assertRedirect()->assertSessionHas('success');
    expect($guest->fresh()->banned)->toBeFalse()
        ->and(PartyLogEntry::query()->pluck('action')->all())->toBe(['member.role_changed', 'member.banned', 'member.unbanned']);
});

it('refuses web moderation attempts according to the hierarchy', function () {
    $moderator = seatMember($this->party, 'moderator');
    $other = seatMember($this->party, 'moderator');
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$other->id}/ban")->assertForbidden();
    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$host->id}/ban")->assertForbidden();
    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'vip'])->assertForbidden();
    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'host'])->assertSessionHasErrors('member');
    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'nope'])->assertSessionHasErrors('role');
    $this->actingAs($host->user)->put('/parties/ABCD/members/999999/ban')->assertNotFound();

    expect(PartyLogEntry::query()->count())->toBe(0);
});
