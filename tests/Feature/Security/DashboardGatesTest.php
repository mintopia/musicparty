<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function userWithRole(?string $code): User
{
    $user = User::factory()->create();
    if ($code !== null) {
        $role = Role::query()->forceCreate(['code' => $code, 'name' => ucfirst($code)]);
        $user->roles()->attach($role);
    }

    return $user;
}

it('limits the dashboards to admins', function (string $gate) {
    expect(Gate::forUser(userWithRole('admin'))->allows($gate))->toBeTrue()
        ->and(Gate::forUser(userWithRole(null))->allows($gate))->toBeFalse();
})->with(['viewPulse', 'viewHorizon']);

it('registers the pulse and horizon routes', function () {
    expect(route('pulse'))->toBeString()->and(route('horizon.index'))->toBeString();
});

it('has no telescope tables or route', function () {
    expect(Schema::hasTable('telescope_entries'))->toBeFalse()
        ->and(Route::has('telescope'))->toBeFalse();
});
