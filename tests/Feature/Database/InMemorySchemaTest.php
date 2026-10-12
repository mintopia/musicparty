<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('uses in-memory sqlite for the default connection', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(DB::connection()->getDriverName())->toBe('sqlite');
});

it('runs the migrations on sqlite', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('parties'))->toBeTrue();
});

it('persists factory models', function () {
    $user = User::factory()->create();

    expect(User::query()->whereKey($user->getKey())->exists())->toBeTrue();
});
