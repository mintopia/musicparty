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

it('provides a locally generated QR code and no external QR service URL', function () {
    $party = Party::factory()->create();

    $this->get(route('parties.tv', $party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('party.qrSvg', fn ($svg) => str_contains((string) $svg, '<svg'))
            ->etc());

    expect(file_get_contents(resource_path('js/components/TvPlayer.vue')))->not->toContain('qrserver');
});

it('supports a state-only partial reload for reconnect resync', function () {
    $party = Party::factory()->create(['name' => 'Renamed']);

    $this->get(route('parties.tv', $party->code))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('state', fn (Assert $reload) => $reload->where('state.name', 'Renamed')));
});

it('defaults new parties to showing the QR code', function () {
    expect((new Party)->show_qrcode)->toBeTrue()
        ->and(Party::factory()->create()->fresh()->show_qrcode)->toBeTrue();
});
