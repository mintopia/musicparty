<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function dashboardAdmin(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');
    AdminAuditEntry::query()->delete();

    return $user->fresh();
}

it('counts users, recent signups, parties and live parties', function (): void {
    Party::withoutEvents(function (): void {
        Party::factory()->create(['state' => PartyState::Live]);
        Party::factory()->create(['state' => PartyState::Paused]);
        Party::factory()->create(['state' => PartyState::Ended]);
    });
    User::query()->update(['created_at' => now()->subWeeks(3)]);
    $total = User::query()->count() + 3;

    $admin = dashboardAdmin();
    User::factory()->count(2)->create();

    $this->withoutVite()->actingAs($admin)->get(route('admin.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('stats.users.total', $total)
            ->where('stats.users.lastWeek', 3)
            ->where('stats.parties.total', 3)
            ->where('stats.parties.live', 1));
});

it('shares flash messages, admin links and no impersonation to admins', function (): void {
    $this->withoutVite()->actingAs(dashboardAdmin())
        ->withSession(['success' => 'Saved.', 'error' => 'Nope.'])
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash.success', 'Saved.')
            ->where('flash.error', 'Nope.')
            ->where('flash.info', null)
            ->where('impersonating', null)
            ->where('adminLinks', [
                ['label' => 'Pulse', 'href' => url('/pulse')],
                ['label' => 'Horizon', 'href' => url('/horizon')],
            ]));
});

it('omits admin links for non-admins', function (): void {
    $this->withoutVite()->actingAs(User::factory()->create()->fresh())->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('adminLinks', []));
});

it('shares the impersonation banner data while impersonating', function (): void {
    $user = User::factory()->create(['nickname' => 'Bob']);

    $this->withoutVite()->actingAs($user)->withSession(['impersonator_id' => 99])->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('impersonating.name', 'Bob')
            ->where('impersonating.stopUrl', '/admin/impersonate'));
});

it('keeps the new admin routes behind the admin gate', function (string $method, string $uri): void {
    $user = User::factory()->create();
    $account = LinkedAccount::factory()->create(['user_id' => $user->id]);
    $uri = strtr($uri, ['{user}' => (string) $user->id, '/accounts/1' => '/accounts/'.$account->id]);

    $this->{$method}($uri)->assertRedirect(route('login'));
    $this->actingAs($user->fresh())->{$method}($uri)->assertForbidden();
})->with([
    'show' => ['get', '/admin/users/{user}'],
    'edit' => ['get', '/admin/users/{user}/edit'],
    'update' => ['patch', '/admin/users/{user}'],
    'destroy' => ['delete', '/admin/users/{user}'],
    'impersonate' => ['post', '/admin/users/{user}/impersonate'],
    'account delete' => ['get', '/admin/users/{user}/accounts/1/delete'],
    'account destroy' => ['delete', '/admin/users/{user}/accounts/1'],
    'provider edit' => ['get', '/admin/providers/discord/edit'],
]);
