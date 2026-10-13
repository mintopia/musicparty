<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('copies the owner market onto existing Parties', function () {
    $owner = User::factory()->create(['market' => 'se']);
    $party = Party::factory()->create(['user_id' => $owner->id]);
    DB::table('parties')->where('id', $party->id)->update(['country' => 'GB']);

    DB::table('parties')->update([
        'country' => DB::raw("COALESCE((SELECT UPPER(users.market) FROM users WHERE users.id = parties.user_id), 'GB')"),
    ]);

    expect($party->fresh()->country)->toBe('SE');
});
