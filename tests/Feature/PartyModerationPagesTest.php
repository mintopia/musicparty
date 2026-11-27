<?php

use App\Enums\PartyModerationType;
use App\Models\Party;
use App\Models\PartyModeration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create(['first_login' => false]);
    $this->party = Party::factory()->create(['user_id' => $this->owner->id]);
    $this->moderation = new PartyModeration;
    $this->moderation->forceFill([
        'party_id' => $this->party->id,
        'type' => PartyModerationType::mtName,
        'value' => 'bad song',
        'enabled' => true,
        'regex' => false,
        'notes' => 'note',
    ])->save();
});

it('forbids non-managers on every moderation page', function (string $method, string $route) {
    $url = route($route, ['party' => $this->party->code, 'moderation' => $this->moderation->id]);

    $this->actingAs(User::factory()->create(['first_login' => false]))->{$method}($url)->assertForbidden();
})->with([
    ['get', 'parties.moderation.index'],
    ['get', 'parties.moderation.create'],
    ['post', 'parties.moderation.store'],
    ['get', 'parties.moderation.edit'],
    ['patch', 'parties.moderation.update'],
    ['delete', 'parties.moderation.destroy'],
]);

it('renders the index with party props and rows', function () {
    $this->actingAs($this->owner)->get(route('parties.moderation.index', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Moderation/Index')
            ->where('party.code', $this->party->code)
            ->where('party.canManage', true)
            ->has('playerState')
            ->where('moderationTabs.aiReviewEnabled', false)
            ->has('moderations.data', 1)
            ->where('moderations.data.0.type_name', 'Track name')
            ->where('moderations.data.0.value', 'bad song'));
});

it('searches and filters rules and reports unfiltered counts', function () {
    PartyModeration::query()->forceCreate([
        'party_id' => $this->party->id,
        'type' => PartyModerationType::mtArtist,
        'value' => '(?i)nickel',
        'enabled' => false,
        'regex' => true,
        'action' => 'flag',
        'notes' => 'meh',
    ]);
    $values = fn (array $query) => collect($this->actingAs($this->owner)->get(route('parties.moderation.index', [$this->party->code] + $query))->assertOk()->viewData('page')['props']['moderations']['data'])->pluck('value')->all();

    expect($values(['q' => 'bad']))->toBe(['bad song'])
        ->and($values(['q' => 'meh']))->toBe(['(?i)nickel'])
        ->and($values(['state' => 'on']))->toBe(['bad song'])
        ->and($values(['state' => 'off']))->toBe(['(?i)nickel'])
        ->and($values(['action' => 'flag']))->toBe(['(?i)nickel'])
        ->and($values(['action' => 'reject']))->toBe(['bad song'])
        ->and($values(['pattern' => 1]))->toBe(['(?i)nickel']);

    $this->actingAs($this->owner)->get(route('parties.moderation.index', [$this->party->code, 'action' => 'flag']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.action', 'flag')
            ->where('counts', ['total' => 2, 'on' => 1, 'off' => 1, 'flag' => 1, 'reject' => 1, 'pattern' => 1]));
    $this->actingAs($this->owner)->get(route('parties.moderation.index', [$this->party->code, 'action' => 'bogus']))->assertSessionHasErrors('action');
});

it('renders create with defaults and option lists', function () {
    $this->actingAs($this->owner)->get(route('parties.moderation.create', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Moderation/Create')
            ->has('playerState')
            ->where('moderation.action', 'reject')
            ->where('track', null)
            ->where('types.mtName', 'Track name')
            ->where('actions.flag', 'Flag for approval'));
});

it('renders edit and delete with the filter', function () {
    $params = ['party' => $this->party->code, 'moderation' => $this->moderation->id];

    $this->actingAs($this->owner)->get(route('parties.moderation.edit', $params))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Moderation/Edit')
            ->has('playerState')
            ->where('moderation.type', 'mtName')
            ->where('moderation.notes', 'note'));
});

it('creates, updates and deletes a filter with flash messages', function () {
    $this->actingAs($this->owner)
        ->post(route('parties.moderation.store', $this->party->code), ['type' => 'mtArtist', 'value' => 'Nope', 'enabled' => true, 'action' => 'flag'])
        ->assertRedirect(route('parties.moderation.index', $this->party->code))
        ->assertSessionHas('successMessage', 'The filter has been created');
    $created = $this->party->moderations()->where('value', 'Nope')->firstOrFail();
    expect($created->type)->toBe(PartyModerationType::mtArtist);

    $this->actingAs($this->owner)
        ->patch(route('parties.moderation.update', [$this->party->code, $created->id]), ['type' => 'mtAlbum', 'value' => 'Other'])
        ->assertSessionHas('successMessage', 'The filter has been updated');
    expect($created->refresh()->value)->toBe('Other')->and($created->enabled)->toBeFalse();

    $this->actingAs($this->owner)
        ->delete(route('parties.moderation.destroy', [$this->party->code, $created->id]))
        ->assertSessionHas('successMessage', 'The filter has been deleted');
    expect(PartyModeration::query()->find($created->id))->toBeNull();
});

it('returns validation errors for an invalid filter', function () {
    $this->actingAs($this->owner)
        ->post(route('parties.moderation.store', $this->party->code), ['type' => '', 'value' => ''])
        ->assertSessionHasErrors(['type', 'value']);
});
