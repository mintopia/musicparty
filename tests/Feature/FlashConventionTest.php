<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create()->user;
});

it('flashes party settings saves to the next response', function () {
    $this->actingAs($this->host)->patch(route('parties.update', 'ABCD'), ['name' => 'Renamed'])->assertRedirect();

    $this->get(route('parties.settings', 'ABCD'))->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Settings saved'));
});

it('flashes member bans to the next response', function () {
    $guest = PartyMember::factory()->for($this->party)->create();

    $this->actingAs($this->host)->put("/parties/ABCD/members/{$guest->id}/ban")->assertRedirect();

    $this->get('/parties/ABCD/members')->assertInertia(fn (Assert $page) => $page->where('flash.success', "{$guest->user->nickname} was banned"));
});

it('flashes blocklist changes to the next response', function () {
    $this->actingAs($this->host)->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'artist_name', 'value' => 'Bad Band', 'is_regex' => false, 'is_enabled' => true])->assertRedirect();

    $this->get(route('parties.blocklist', 'ABCD'))->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Bad Band was added to the blocklist'));
});

it('flashes signup completion to the next response', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => 'newbie', 'terms' => true])->assertRedirect();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Welcome to Music Party'));
});

it('flashes logout to the next response', function () {
    $this->actingAs($this->host)->post(route('logout'))->assertRedirect(route('home'));

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('flash.success', 'You have been logged out'));
});

it('flashes a suspended login failure as an error', function () {
    $this->withSession(['error' => 'Unable to login'])->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('flash.error', 'Unable to login'));
});
