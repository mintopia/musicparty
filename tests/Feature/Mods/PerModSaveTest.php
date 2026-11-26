<?php

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use App\Services\ModSettingsStore;
use Tests\Support\FooModFixture;

beforeEach(function () {
    $this->withoutVite();
    FooModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $foo = Mod::whereCode('foo')->firstOrFail();
    $foo->settings()->delete();
    ModSetting::factory()->create(['mod_id' => $foo->id, 'code' => 'count', 'type' => SettingType::stInteger, 'default' => 3, 'validation' => 'min:1|max:10']);
    $bar = Mod::factory()->create(['code' => 'bar', 'name' => 'Bar']);
    ModSetting::factory()->create(['mod_id' => $bar->id, 'code' => 'anchor', 'type' => SettingType::stUser, 'validation' => 'nullable']);
    ModSetting::factory()->create(['mod_id' => $bar->id, 'code' => 'must', 'type' => SettingType::stUser, 'validation' => 'required']);

    $this->owner = User::factory()->withSpotify()->create();
    $this->owner->forceFill(['first_login' => false])->save();
    $this->party = Party::factory()->for($this->owner)->create();
    $this->store = new ModSettingsStore;
    $this->url = fn (string $mod) => route('parties.mods.updateMod', [$this->party->code, $mod]);
});

it('saves one mod while another mod would be invalid', function () {
    $this->actingAs($this->owner)->put(($this->url)('foo'), ['settings' => ['count' => 4]])
        ->assertSessionHasNoErrors()->assertRedirect(route('parties.edit', ['party' => $this->party->code, 'tab' => 'mods']));

    expect($this->store->value($this->party, 'foo', 'count'))->toBe(4);
});

it('rejects an invalid value on the mod being saved and writes nothing', function () {
    $this->actingAs($this->owner)->put(($this->url)('foo'), ['settings' => ['count' => 99]])
        ->assertSessionHasErrors(['mods.foo.settings.count']);

    expect($this->store->value($this->party, 'foo', 'count'))->not->toBe(99);
});

it('only reports errors for the saved mod', function () {
    $this->actingAs($this->owner)->put(($this->url)('bar'), ['settings' => ['anchor' => 0, 'must' => 0]])
        ->assertSessionHasErrors(['mods.bar.settings.must'])
        ->assertSessionDoesntHaveErrors(['mods.bar.settings.anchor', 'mods.foo.settings.count']);
});

it('treats 0 as none for an optional user setting and stores the id otherwise', function () {
    $user = User::factory()->create();

    $this->actingAs($this->owner)->put(($this->url)('bar'), ['settings' => ['anchor' => 0, 'must' => $user->id]])
        ->assertSessionHasNoErrors();
    expect((new ModSettingsStore)->value($this->party, 'bar', 'anchor'))->toBeNull()
        ->and((new ModSettingsStore)->value($this->party, 'bar', 'must'))->toBe($user->id);

    $this->actingAs($this->owner)->put(($this->url)('bar'), ['settings' => ['anchor' => $user->id, 'must' => $user->id]])
        ->assertSessionHasNoErrors();
    expect((new ModSettingsStore)->value($this->party, 'bar', 'anchor'))->toBe($user->id);

    $this->actingAs($this->owner)->put(($this->url)('bar'), ['settings' => ['anchor' => 999999, 'must' => $user->id]])
        ->assertSessionHasErrors('mods.bar.settings.anchor');
});

it('rejects an unknown mod', function () {
    $this->actingAs($this->owner)->put(($this->url)('nope'), ['settings' => []])->assertSessionHasErrors('mods.nope');
});

it('searches only party members', function () {
    $member = User::factory()->create(['nickname' => 'Zebrafish']);
    PartyMember::factory()->create(['party_id' => $this->party->id, 'user_id' => $member->id]);
    User::factory()->create(['nickname' => 'Zebraoutsider']);

    $this->actingAs($this->owner)->getJson(route('parties.mods.users', [$this->party->code, 'q' => 'Zebra']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $member->id);

    $other = User::factory()->withSpotify()->create();
    $other->forceFill(['first_login' => false])->save();
    $this->actingAs($other)->getJson(route('parties.mods.users', [$this->party->code]))->assertForbidden();
});

it('redirects the old Mods page to the Mods tab of Settings', function () {
    $this->actingAs($this->owner)->get(route('parties.mods.edit', $this->party->code))
        ->assertRedirect(route('parties.edit', ['party' => $this->party->code, 'tab' => 'mods']));
});

it('opens Settings on the requested tab and defaults to general', function () {
    $this->actingAs($this->owner)->get(route('parties.edit', [$this->party->code, 'tab' => 'mods']))
        ->assertInertia(fn ($page) => $page->component('Parties/Edit')->where('tab', 'mods')->has('mods'));

    $this->actingAs($this->owner)->get(route('parties.edit', [$this->party->code, 'tab' => 'bogus']))
        ->assertInertia(fn ($page) => $page->where('tab', 'general'));
});

it('returns to the page the Settings form was saved from', function () {
    $this->actingAs($this->owner)->from(route('parties.edit', [$this->party->code, 'tab' => 'playback']))
        ->put(($this->url)('foo'), ['settings' => ['count' => 4]])
        ->assertRedirect(route('parties.edit', [$this->party->code, 'tab' => 'playback']));
});
