<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function partyWithMember(string $role = 'guest'): array
{
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($party)->for($user);
    ($role === 'guest' ? $member : $member->{$role}())->create();

    return [$party, $user];
}

it('records a log entry when a party is created', function () {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), [
        'name' => 'Friday LAN', 'music_provider' => 'fake', 'player_kind' => 'fake',
    ]);

    $entry = PartyLogEntry::query()->sole();
    expect($entry->action)->toBe('party.created')
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->details)->toMatchArray(['name' => 'Friday LAN']);
});

it('records old and new values when the host changes a setting', function () {
    [$party, $host] = partyWithMember('host');
    $party->forceFill(['name' => 'Old name'])->save();

    $this->actingAs($host)->patch(route('parties.update', ['party' => 'ABCD']), ['name' => 'New name'])
        ->assertRedirect();

    $entry = PartyLogEntry::query()->sole();
    expect($party->fresh()->name)->toBe('New name')
        ->and($entry->action)->toBe('party.settings_changed')
        ->and($entry->subject)->toBe('name')
        ->and($entry->details)->toBe(['old' => 'Old name', 'new' => 'New name'])
        ->and($entry->user_id)->toBe($host->id);
});

it('records settings changes made through the API', function () {
    [$party, $host] = partyWithMember('host');
    Sanctum::actingAs($host);

    $this->patchJson('/api/v1/parties/ABCD', ['name' => 'Renamed'])->assertOk();

    expect(PartyLogEntry::query()->sole()->details)->toMatchArray(['new' => 'Renamed']);
});

it('does not log a setting that did not change', function () {
    [$party, $host] = partyWithMember('host');
    $party->forceFill(['name' => 'Same'])->save();
    Sanctum::actingAs($host);

    $this->patchJson('/api/v1/parties/ABCD', ['name' => 'Same'])->assertOk();

    expect(PartyLogEntry::query()->count())->toBe(0);
});

it('refuses settings changes from moderators and guests without logging', function (string $state) {
    [, $user] = partyWithMember($state);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/parties/ABCD', ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->patch(route('parties.update', ['party' => 'ABCD']), ['name' => 'Nope'])->assertForbidden();

    expect(PartyLogEntry::query()->count())->toBe(0);
})->with(['moderator', 'guest']);

it('shows the log to hosts and moderators through the API, newest first', function (string $state) {
    [$party, $user] = partyWithMember($state);
    $first = PartyLogEntry::factory()->for($party)->create(['action' => 'party.created']);
    $second = PartyLogEntry::factory()->for($party)->bySystem('player')->create(['action' => 'player.paused']);
    PartyLogEntry::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/parties/ABCD/log')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($response->json('data.0.actor'))->toBe('player')
        ->and($response->json('data.0.actor_kind'))->toBe('system')
        ->and($response->json('data.1.actor_kind'))->toBe('member');
})->with(['host', 'moderator']);

it('refuses the log to guests, banned moderators, non-members and anonymous visitors', function () {
    [$party, $guest] = partyWithMember('guest');
    $banned = User::factory()->create();
    PartyMember::factory()->for($party)->for($banned)->moderator()->banned()->create();
    $stranger = User::factory()->create();

    foreach ([$guest, $banned, $stranger] as $user) {
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/parties/ABCD/log')->assertForbidden();
        $this->actingAs($user)->get(route('parties.log', ['party' => 'ABCD']))->assertForbidden();
    }

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/parties/ABCD/log')->assertUnauthorized();
});

it('renders the log viewer page for the host', function () {
    [$party, $host] = partyWithMember('host');
    PartyLogEntry::factory()->for($party)->create();

    $this->withoutVite()->actingAs($host)->get(route('parties.log', ['party' => 'ABCD']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Party/Log')
            ->where('party.code', 'ABCD')
            ->has('entries.data', 1));
});

it('keeps the log available after the party has ended', function () {
    [$party, $host] = partyWithMember('host');
    $party->forceFill(['state' => 'ended'])->save();
    PartyLogEntry::factory()->for($party)->create();
    Sanctum::actingAs($host);

    $this->getJson('/api/v1/parties/ABCD/log')->assertOk()->assertJsonCount(1, 'data');
});

it('paginates the log', function () {
    [$party, $host] = partyWithMember('host');
    PartyLogEntry::factory()->count(55)->for($party)->create();
    Sanctum::actingAs($host);

    $this->getJson('/api/v1/parties/ABCD/log')->assertOk()->assertJsonCount(50, 'data');
    $this->getJson('/api/v1/parties/ABCD/log?page=2')->assertOk()->assertJsonCount(5, 'data');
});

it('records a system-actor entry without a user', function () {
    $party = Party::factory()->create();

    $entry = app(RecordPartyLogEntry::class)($party, 'player.paused', systemActor: 'player');

    expect($entry->user_id)->toBeNull()->and($entry->system_actor)->toBe('player');
});

it('validates the settings name', function (string $name) {
    [, $host] = partyWithMember('host');
    Sanctum::actingAs($host);

    $this->patchJson('/api/v1/parties/ABCD', ['name' => $name])->assertUnprocessable();
})->with(['too short' => 'a', 'too long' => str_repeat('x', 65)]);

it('is append-only', function () {
    $entry = PartyLogEntry::factory()->create(['action' => 'party.created']);

    $entry->action = 'tampered';
    $entry->save();
    $entry->delete();

    expect($entry->fresh()->action)->toBe('party.created');
});

it('lets the host change the downvote settings through the API and logs them', function () {
    [$party, $host] = partyWithMember('host');
    Sanctum::actingAs($host);

    $this->patchJson('/api/v1/parties/ABCD', ['downvotes' => false, 'downvotes_per_hour' => 3])
        ->assertOk()
        ->assertJsonPath('data.downvotes', false)
        ->assertJsonPath('data.downvotes_per_hour', 3);

    $party->refresh();
    expect($party->downvotes)->toBeFalse()->and($party->downvotes_per_hour)->toBe(3)
        ->and(PartyLogEntry::query()->where('action', 'party.settings_changed')->count())->toBe(2);

    $this->patchJson('/api/v1/parties/ABCD', ['downvotes_per_hour' => null])->assertOk()->assertJsonPath('data.downvotes_per_hour', null);
});

it('rejects invalid downvote settings', function (array $payload) {
    [, $host] = partyWithMember('host');
    Sanctum::actingAs($host);

    $this->patchJson('/api/v1/parties/ABCD', $payload)->assertUnprocessable();
})->with([
    'negative cap' => [['downvotes_per_hour' => -1]],
    'non-integer cap' => [['downvotes_per_hour' => 'lots']],
    'non-boolean toggle' => [['downvotes' => 'maybe']],
]);
