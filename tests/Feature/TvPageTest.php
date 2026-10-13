<?php

use App\Models\Party;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the TV page for guests as an Inertia page with the party state', function () {
    $party = Party::factory()->create();

    $this->get(route('parties.tv', $party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Tv')
            ->where('party.code', $party->code)
            ->where('party.name', $party->name)
            ->where('party.url', route('parties.show', $party->code))
            ->where('state.name', $party->name)
            ->where('state.now', null)
            ->where('state.current', null)
            ->has('state.show_qrcode'));
});

it('supports a state-only partial reload for reconnect resync', function () {
    $party = Party::factory()->create(['name' => 'Renamed']);

    $this->get(route('parties.tv', $party->code))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('state', fn (Assert $reload) => $reload->where('state.name', 'Renamed')));
});
