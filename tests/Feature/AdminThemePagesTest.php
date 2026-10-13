<?php

use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $role = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $this->admin = User::factory()->create(['first_login' => false]);
    $this->admin->roles()->attach($role);
    $this->theme = Theme::factory()->create(['name' => 'Custom Look']);
});

it('keeps non-admins and guests out of every admin theme route', function (string $method, string $route) {
    $url = route($route, ['theme' => $this->theme]);

    $this->{$method}($url)->assertRedirect();
    $this->actingAs(User::factory()->create(['first_login' => false]))->{$method}($url)->assertForbidden();
})->with([
    ['get', 'admin.settings.themes.create'],
    ['post', 'admin.settings.themes.store'],
    ['get', 'admin.settings.themes.edit'],
    ['patch', 'admin.settings.themes.update'],
    ['get', 'admin.settings.themes.delete'],
    ['delete', 'admin.settings.themes.destroy'],
]);

it('renders the create form with colour token definitions', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.themes.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Themes/Create')
            ->where('theme.name', '')
            ->where('theme.active', false)
            ->where('theme.readonly', false)
            ->has('colourTokens', count(Theme::COLOUR_TOKENS))
            ->where('colourTokens.0', ['token' => 'primary', 'label' => 'Primary', 'required' => true])
            ->where('colourTokens.2.required', false));
});

it('creates a theme and validates required colours', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.themes.store'), ['name' => 'Fresh', 'tokens' => ['primary' => 'nope']])
        ->assertSessionHasErrors(['tokens.primary', 'tokens.nav-background']);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.themes.store'), [
            'name' => 'Fresh',
            'css' => 'body{}',
            'active' => 1,
            'tokens' => ['primary' => '#112233', 'nav-background' => '#445566', 'surface' => ''],
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('successMessage');

    $created = Theme::whereCode('fresh')->firstOrFail();
    expect($created->active)->toBeTrue()
        ->and($created->tokens)->toBe(['primary' => '#112233', 'nav-background' => '#445566']);
});

it('renders the edit form and updates a theme', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.themes.edit', $this->theme))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Themes/Edit')
            ->where('theme.id', $this->theme->id)
            ->where('theme.tokens.primary', '#0054a6')
            ->where('theme.readonly', false)
            ->has('colourTokens'));

    $this->actingAs($this->admin)
        ->patch(route('admin.settings.themes.update', $this->theme), [
            'name' => 'Renamed',
            'dark_mode' => 1,
            'tokens' => ['primary' => '#000000', 'nav-background' => '#ffffff'],
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('successMessage');

    $fresh = $this->theme->fresh();
    expect($fresh->name)->toBe('Renamed')->and($fresh->dark_mode)->toBeTrue();
});

it('only changes active and dark mode on a readonly theme', function () {
    $readonly = Theme::factory()->create(['name' => 'Locked', 'readonly' => true]);

    $this->actingAs($this->admin)->get(route('admin.settings.themes.edit', $readonly))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Themes/Edit')->where('theme.readonly', true));

    $this->actingAs($this->admin)
        ->patch(route('admin.settings.themes.update', $readonly), ['name' => 'Hacked', 'active' => 1, 'dark_mode' => 1, 'tokens' => ['primary' => '#000000']])
        ->assertRedirect(route('admin.settings.index'));

    $fresh = $readonly->fresh();
    expect($fresh->name)->toBe('Locked')
        ->and($fresh->active)->toBeTrue()
        ->and($fresh->dark_mode)->toBeTrue()
        ->and($fresh->tokens['primary'])->toBe('#0054a6');
});

it('confirms and deletes a theme', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.themes.delete', $this->theme))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Themes/Delete')
            ->where('theme', ['id' => $this->theme->id, 'name' => 'Custom Look']));

    $this->actingAs($this->admin)->delete(route('admin.settings.themes.destroy', $this->theme))
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('successMessage');
    expect(Theme::find($this->theme->id))->toBeNull();
});

it('refuses to delete a readonly theme', function () {
    $readonly = Theme::factory()->create(['readonly' => true]);

    $this->actingAs($this->admin)->get(route('admin.settings.themes.delete', $readonly))->assertStatus(400);
    $this->actingAs($this->admin)->delete(route('admin.settings.themes.destroy', $readonly))->assertStatus(400);
    expect($readonly->fresh())->not->toBeNull();
});
