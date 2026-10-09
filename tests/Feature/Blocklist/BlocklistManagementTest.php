<?php

use App\Domain\Queue\BlocklistMatchType;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->entry = BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Old Value')->create();
});

function blocklistActor(Party $party, string $role, bool $banned = false): PartyMember
{
    $member = PartyMember::factory()->for($party);
    $member = $role === 'guest' ? $member : $member->{$role}();

    return ($banned ? $member->banned() : $member)->create();
}

function blocklistCall(?PartyMember $actor, string $operation, BlocklistEntry $entry, array $payload = [])
{
    $actor ??= PartyMember::factory()->create();
    Sanctum::actingAs($actor->user);
    $base = '/api/v1/parties/ABCD/blocklist';
    $body = $payload + ['match_type' => 'track_name', 'value' => 'New Value'];

    return match ($operation) {
        'index' => test()->getJson($base),
        'store' => test()->postJson($base, $body),
        'update' => test()->putJson("{$base}/{$entry->id}", $body),
        'destroy' => test()->deleteJson("{$base}/{$entry->id}"),
    };
}

dataset('operations', ['index', 'store', 'update', 'destroy']);

it('allows the host and moderators to use every blocklist route', function (string $role, string $operation) {
    blocklistCall(blocklistActor($this->party, $role), $operation, $this->entry)->assertSuccessful();
})->with(['host', 'moderator'])->with('operations');

it('forbids everyone else from every blocklist route', function (string $role, bool $banned, string $operation) {
    $actor = $role === 'stranger' ? null : blocklistActor($this->party, $role, $banned);

    blocklistCall($actor, $operation, $this->entry)->assertForbidden();

    expect(BlocklistEntry::query()->count())->toBe(1)->and(PartyLogEntry::query()->count())->toBe(0);
})->with([
    'vip' => ['vip', false],
    'guest' => ['guest', false],
    'banned host' => ['host', true],
    'banned moderator' => ['moderator', true],
    'non-member' => ['stranger', false],
])->with('operations');

it('adds an entry and writes a party log entry', function () {
    $host = blocklistActor($this->party, 'host');

    blocklistCall($host, 'store', $this->entry, ['match_type' => 'isrc', 'value' => 'USRC17607839', 'notes' => 'Too loud'])
        ->assertCreated()
        ->assertJsonPath('data.match_type', 'isrc')
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.is_regex', false)
        ->assertJsonPath('data.notes', 'Too loud');

    $log = PartyLogEntry::query()->where('action', 'blocklist.entry_added')->sole();
    expect($log->user_id)->toBe($host->user_id)
        ->and($log->subject)->toBe('USRC17607839')
        ->and($log->details['new']['match_type'])->toBe('isrc');
});

it('updates an entry recording old and new values', function () {
    $moderator = blocklistActor($this->party, 'moderator');

    blocklistCall($moderator, 'update', $this->entry, ['value' => '^old', 'is_regex' => true, 'is_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.value', '^old')
        ->assertJsonPath('data.is_regex', true)
        ->assertJsonPath('data.is_enabled', false);

    $log = PartyLogEntry::query()->where('action', 'blocklist.entry_updated')->sole();
    expect($log->details['old']['value'])->toBe('Old Value')
        ->and($log->details['new']['value'])->toBe('^old')
        ->and($log->details['old']['is_enabled'])->toBeTrue()
        ->and($log->details['new']['is_enabled'])->toBeFalse();
});

it('logs nothing when an update changes nothing', function () {
    blocklistCall(blocklistActor($this->party, 'host'), 'update', $this->entry, ['value' => 'Old Value'])->assertOk();

    expect(PartyLogEntry::query()->count())->toBe(0);
});

it('removes an entry and writes a party log entry', function () {
    $host = blocklistActor($this->party, 'host');

    blocklistCall($host, 'destroy', $this->entry)->assertNoContent();

    expect(BlocklistEntry::query()->count())->toBe(0);
    $log = PartyLogEntry::query()->where('action', 'blocklist.entry_removed')->sole();
    expect($log->details['old']['value'])->toBe('Old Value');
});

it('lists entries in creation order', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::AlbumId, 'alb')->create();
    BlocklistEntry::factory()->create();

    blocklistCall(blocklistActor($this->party, 'host'), 'index', $this->entry)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->entry->id)
        ->assertJsonPath('data.1.match_type', 'album_id');
});

it('rejects an invalid regular expression', function (string $operation) {
    blocklistCall(blocklistActor($this->party, 'host'), $operation, $this->entry, ['value' => '([unclosed', 'is_regex' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');

    expect(PartyLogEntry::query()->count())->toBe(0);
})->with(['store', 'update']);

it('rejects a regular expression on an id or ISRC entry', function (string $type) {
    blocklistCall(blocklistActor($this->party, 'host'), 'store', $this->entry, ['match_type' => $type, 'value' => '.*', 'is_regex' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_regex');
})->with(['track_id', 'artist_id', 'album_id', 'isrc']);

it('rejects missing or unknown fields', function (array $payload, string $field) {
    blocklistCall(blocklistActor($this->party, 'host'), 'store', $this->entry, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown type' => [['match_type' => 'genre'], 'match_type'],
    'empty value' => [['value' => ''], 'value'],
    'long value' => [['value' => str_repeat('a', 501)], 'value'],
]);

it('returns not found for an entry belonging to another party', function (string $operation) {
    $foreign = BlocklistEntry::factory()->for(Party::factory()->live()->create())->create();

    blocklistCall(blocklistActor($this->party, 'host'), $operation, $foreign)->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
})->with(['update', 'destroy']);

it('renders the blocklist page with entries, match types and abilities', function () {
    $host = blocklistActor($this->party, 'host');

    $this->actingAs($host->user)->get(route('parties.blocklist', 'ABCD'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Party/Blocklist')
            ->where('party.code', 'ABCD')
            ->has('entries', 1, fn (Assert $entry) => $entry
                ->where('id', $this->entry->id)
                ->where('match_type', 'track_name')
                ->where('value', 'Old Value')
                ->where('is_regex', false)
                ->where('is_enabled', true)
                ->where('notes', null))
            ->has('matchTypes', 7)
            ->where('matchTypes.0', ['value' => 'track_name', 'label' => 'Track name', 'supportsRegex' => true])
            ->where('matchTypes.1.supportsRegex', false)
            ->where('abilities.canManage', true));
});

it('forbids the blocklist page to non-moderators', function (string $role) {
    $this->actingAs(blocklistActor($this->party, $role)->user)->get(route('parties.blocklist', 'ABCD'))->assertForbidden();
})->with(['vip', 'guest']);

it('manages entries through the web routes and redirects back with a message', function () {
    $host = blocklistActor($this->party, 'host');
    $this->actingAs($host->user)->from('/parties/ABCD/blocklist');

    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'artist_name', 'value' => 'Bad Band', 'is_regex' => false, 'is_enabled' => true, 'notes' => 'no'])
        ->assertRedirect('/parties/ABCD/blocklist')
        ->assertSessionHas('successMessage');
    $created = BlocklistEntry::query()->where('value', 'Bad Band')->sole();

    $this->put(route('parties.blocklist.update', ['ABCD', $created->id]), ['match_type' => 'artist_name', 'value' => 'Bad Band', 'is_enabled' => false])
        ->assertRedirect('/parties/ABCD/blocklist')
        ->assertSessionHas('successMessage');
    expect($created->fresh()->is_enabled)->toBeFalse();

    $this->delete(route('parties.blocklist.destroy', ['ABCD', $created->id]))
        ->assertRedirect('/parties/ABCD/blocklist')
        ->assertSessionHas('successMessage');
    expect(BlocklistEntry::query()->whereKey($created->id)->exists())->toBeFalse()
        ->and(PartyLogEntry::query()->where('action', 'like', 'blocklist.%')->count())->toBe(3);
});

it('reports an invalid regular expression on the web store route', function () {
    $this->actingAs(blocklistActor($this->party, 'host')->user)
        ->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_name', 'value' => '(', 'is_regex' => true])
        ->assertSessionHasErrors('value');
});

it('forbids web mutations to non-moderators', function (string $role) {
    $this->actingAs(blocklistActor($this->party, $role)->user);

    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_id', 'value' => 'x'])->assertForbidden();
    $this->put(route('parties.blocklist.update', ['ABCD', $this->entry->id]), ['match_type' => 'track_id', 'value' => 'x'])->assertForbidden();
    $this->delete(route('parties.blocklist.destroy', ['ABCD', $this->entry->id]))->assertForbidden();
})->with(['vip', 'guest']);

it('exposes canManageBlocklist on the party page', function (string $role, bool $expected) {
    $this->actingAs(blocklistActor($this->party, $role)->user)->get(route('parties.show', 'ABCD'))
        ->assertInertia(fn (Assert $page) => $page->where('canManageBlocklist', $expected));
})->with([['host', true], ['moderator', true], ['vip', false], ['guest', false]]);
