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
    ['get', 'parties.moderation.delete'],
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
            ->has('moderations.data', 1)
            ->where('moderations.data.0.type_name', 'Track Name')
            ->where('moderations.data.0.value', 'bad song'));
});

it('renders create with defaults and option lists', function () {
    $this->actingAs($this->owner)->get(route('parties.moderation.create', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Moderation/Create')
            ->has('playerState')
            ->where('moderation.action', 'reject')
            ->where('track', null)
            ->where('types.mtName', 'Track Name')
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

    $this->actingAs($this->owner)->get(route('parties.moderation.delete', $params))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Moderation/Delete')
            ->has('playerState')
            ->where('moderation.value', 'bad song'));
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
