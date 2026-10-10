<?php

use App\Domain\Theming\Actions\SetColourScheme;
use App\Domain\Theming\ColourScheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('defaults a new user to the system colour scheme', function (): void {
    $user = User::factory()->create()->fresh();

    expect($user->colour_scheme)->toBe(ColourScheme::System);
});

it('persists the chosen scheme through the shared action', function (ColourScheme $scheme): void {
    $user = User::factory()->create();

    app(SetColourScheme::class)->handle($user, $scheme);

    expect($user->fresh()->colour_scheme)->toBe($scheme);
})->with([ColourScheme::Light, ColourScheme::Dark, ColourScheme::System]);

it('persists the scheme through the api', function (string $scheme): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me/colour-scheme', ['colour_scheme' => $scheme])
        ->assertOk()
        ->assertExactJson(['data' => ['colour_scheme' => $scheme]]);

    expect($user->fresh()->colour_scheme->value)->toBe($scheme);
})->with(['light', 'dark', 'system']);

it('persists the scheme through the web route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('colour-scheme.update'), ['colour_scheme' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->colour_scheme)->toBe(ColourScheme::Dark);
});

it('rejects invalid or missing schemes and leaves the stored value unchanged', function (array $payload): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me/colour-scheme', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colour_scheme');

    expect($user->fresh()->colour_scheme)->toBe(ColourScheme::System);
})->with([
    'unknown' => [['colour_scheme' => 'sepia']],
    'missing' => [[]],
    'array' => [['colour_scheme' => ['dark']]],
    'css' => [['colour_scheme' => 'dark; background:url(x)']],
]);

it('requires authentication', function (): void {
    $this->putJson('/api/v1/me/colour-scheme', ['colour_scheme' => 'dark'])->assertUnauthorized();
    $this->put(route('colour-scheme.update'), ['colour_scheme' => 'dark'])->assertRedirect(route('login'));
});

it('shares the scheme with the shell, leaving it unset for anonymous visitors', function (): void {
    $this->withoutVite()->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('colourScheme', null));

    $user = User::factory()->create(['colour_scheme' => ColourScheme::Dark]);

    $this->withoutVite()->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('colourScheme', 'dark'));
});
