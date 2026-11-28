<?php

use App\Enums\Purpose;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new FakeMusicProvider);
});

function codeUser(bool $admin): User
{
    $user = User::factory()->withSpotify()->create(['first_login' => false]);
    if ($admin) {
        $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    }

    return $user;
}

function codePayload(array $extra = []): array
{
    return $extra + ['name' => 'Renamed', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => 'abc'];
}

it('rejects two parties with the same code at the database level', function () {
    Party::factory()->create(['code' => 'ABCD']);

    expect(fn () => Party::factory()->create(['code' => 'ABCD']))->toThrow(UniqueConstraintViolationException::class);
});

it('regenerates the code when the generator returns one already in use', function () {
    Party::factory()->create(['code' => 'TAKE']);
    $generator = Mockery::mock(PartyCodeGenerator::class)->makePartial();
    $generator->shouldReceive('random')->andReturn('TAKE', 'FREE');
    app()->instance(PartyCodeGenerator::class, $generator);

    $party = Party::factory()->make(['code' => null]);
    $party->saveWithGeneratedCode();

    expect($party->fresh()->code)->toBe('FREE');
});

it('retries the insert when a unique violation slips past the existence check', function () {
    Party::factory()->create(['code' => 'TAKE']);
    $generator = Mockery::mock(PartyCodeGenerator::class);
    $generator->shouldReceive('unused')->andReturn('TAKE', 'FREE');
    app()->instance(PartyCodeGenerator::class, $generator);

    $party = Party::factory()->make(['code' => null]);
    $party->saveWithGeneratedCode();

    expect($party->fresh()->code)->toBe('FREE');
});

it('lets a site admin change the code and name', function () {
    $admin = codeUser(true);
    $party = Party::factory()->for($admin)->create(['provider_id' => 'spotify', 'code' => 'OLDC']);

    $this->actingAs($admin)->patch(route('parties.update', $party->code), codePayload(['code' => 'newc']))
        ->assertRedirect(route('parties.edit', 'NEWC'));

    expect($party->fresh())->code->toBe('NEWC')->name->toBe('Renamed');
});

it('rejects duplicate and malformed codes', function (string $code) {
    $admin = codeUser(true);
    Party::factory()->create(['code' => 'USED']);
    $party = Party::factory()->for($admin)->create(['provider_id' => 'spotify', 'code' => 'OLDC']);

    $this->actingAs($admin)->patch(route('parties.update', $party->code), codePayload(['code' => $code]))
        ->assertSessionHasErrors('code');

    expect($party->fresh()->code)->toBe('OLDC');
})->with(['USED', 'no spaces!', 'AB']);

it('ignores the code from an owner who is not a site admin', function () {
    $owner = codeUser(false);
    $party = Party::factory()->for($owner)->create(['provider_id' => 'spotify', 'code' => 'OLDC']);

    $this->actingAs($owner)->patch(route('parties.update', $party->code), codePayload(['code' => 'NEWC']))
        ->assertSessionHasNoErrors();

    expect($party->fresh())->code->toBe('OLDC')->name->toBe('Renamed');
});
