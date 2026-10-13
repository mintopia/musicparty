<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('sets existing Parties from the owner market when the migration runs', function () {
    $swedish = Party::factory()->create(['user_id' => User::factory()->create(['market' => 'se'])->id]);
    $blank = Party::factory()->create(['user_id' => User::factory()->create(['market' => ''])->id]);

    Schema::dropColumns('parties', 'country');
    expect(Schema::hasColumn('parties', 'country'))->toBeFalse();

    (require database_path('migrations/2026_10_13_180000_add_country_to_parties.php'))->up();

    expect(Schema::hasColumn('parties', 'country'))->toBeTrue()
        ->and($swedish->fresh()->country)->toBe('SE')
        ->and($blank->fresh()->country)->toBe('GB');
});
