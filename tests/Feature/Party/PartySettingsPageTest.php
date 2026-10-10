<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{Party, User}
 */
function settingsParty(string $role): array
{
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($party)->for($user);
    ($role === 'guest' ? $member : $member->{$role}())->create();

    return [$party, $user];
}

it('renders the settings page for the host', function () {
    [$party, $host] = settingsParty('host');

    $this->actingAs($host)->get(route('parties.settings', ['party' => 'ABCD']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Party/Settings')
            ->where('party.code', 'ABCD')
            ->has('settings', fn (Assert $s) => $s
                ->has('allow_requests')->has('max_requests')->has('min_song_length')
                ->has('max_song_length')->has('explicit')->has('no_repeat_interval')->has('hold_requests')));
});

it('refuses the settings page to non-hosts', function (string $role) {
    [, $user] = settingsParty($role);

    $this->actingAs($user)->get(route('parties.settings', ['party' => 'ABCD']))->assertForbidden();
})->with(['moderator', 'guest']);

it('lets the host update each request rule and logs old and new', function (string $key, mixed $old, mixed $new) {
    [$party, $host] = settingsParty('host');
    $party->forceFill([$key => $old])->save();

    $this->actingAs($host)->patch(route('parties.update', ['party' => 'ABCD']), [$key => $new])->assertRedirect();

    $entry = PartyLogEntry::query()->where('action', 'party.settings_changed')->sole();
    expect($party->fresh()->{$key})->toBe($new)
        ->and($entry->action)->toBe('party.settings_changed')
        ->and($entry->subject)->toBe($key)
        ->and($entry->details)->toBe(['old' => $old, 'new' => $new])
        ->and($entry->user_id)->toBe($host->id);
})->with([
    'allow_requests' => ['allow_requests', true, false],
    'max_requests' => ['max_requests', null, 3],
    'min_song_length' => ['min_song_length', null, 30],
    'max_song_length' => ['max_song_length', null, 600],
    'explicit' => ['explicit', true, false],
    'no_repeat_interval' => ['no_repeat_interval', null, 900],
]);

it('refuses setting changes from non-hosts and logs nothing', function (string $role) {
    [$party, $user] = settingsParty($role);

    $this->actingAs($user)->patch(route('parties.update', ['party' => 'ABCD']), ['max_requests' => 2])->assertForbidden();

    expect(PartyLogEntry::query()->where('action', 'party.settings_changed')->count())->toBe(0)
        ->and($party->fresh()->max_requests)->toBeNull();
})->with(['moderator', 'guest']);

it('rejects a minimum length greater than the maximum', function () {
    [$party, $host] = settingsParty('host');

    $this->actingAs($host)->patch(route('parties.update', ['party' => 'ABCD']), ['min_song_length' => 500, 'max_song_length' => 100])
        ->assertSessionHasErrors('min_song_length');

    expect(PartyLogEntry::query()->where('action', 'party.settings_changed')->count())->toBe(0);
});

it('rejects invalid max_requests values', function (mixed $value) {
    [, $host] = settingsParty('host');

    $this->actingAs($host)->patch(route('parties.update', ['party' => 'ABCD']), ['max_requests' => $value])
        ->assertSessionHasErrors('max_requests');
})->with(['negative' => -1, 'text' => 'abc']);

it('accepts the new keys through the API', function () {
    [$party, $host] = settingsParty('host');
    Sanctum::actingAs($host);

    $this->patchJson("/api/v1/parties/{$party->code}", ['allow_requests' => false, 'max_requests' => 5])->assertOk();

    expect($party->fresh()->allow_requests)->toBeFalse()
        ->and($party->fresh()->max_requests)->toBe(5)
        ->and(PartyLogEntry::query()->where('action', 'party.settings_changed')->count())->toBe(2);
});
