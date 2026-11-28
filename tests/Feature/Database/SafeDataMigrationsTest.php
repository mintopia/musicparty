<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

function safeMigration(string $name): object
{
    return require database_path("migrations/{$name}.php");
}

it('keeps a Banned duplicate Party member banned and logs the merge', function () {
    $migration = safeMigration('2026_11_28_110000_dedupe_party_members_and_drop_ban_columns');
    $migration->down();
    $party = Party::factory()->create();
    $user = User::factory()->create();
    $make = fn () => PartyMember::factory()->for($party)->for($user);
    $first = $make()->create();
    $second = $make()->banned()->create();
    $third = $make()->create();
    Log::spy();

    $migration->up();

    expect(PartyMember::where('party_id', $party->id)->where('user_id', $user->id)->pluck('role_id', 'id')->all())
        ->toBe([$second->id => $second->role_id]);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'Merged duplicate Party members'
        && $context['kept_id'] === $second->id
        && $context['removed_ids'] === [$first->id, $third->id]);
});

it('keeps the newest duplicate linked account, logs deletions and flags cross-user duplicates', function () {
    $migration = safeMigration('2026_11_28_120000_add_unique_index_to_linked_accounts');
    $migration->down();
    $provider = SocialProvider::factory()->create()->id;
    $users = User::factory()->count(2)->create();
    $row = fn (int $userId) => DB::table('linked_accounts')->insertGetId([
        'user_id' => $userId, 'social_provider_id' => $provider, 'external_id' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $old = $row($users[0]->id);
    $new = $row($users[1]->id);
    Log::spy();

    $migration->up();

    expect(DB::table('linked_accounts')->pluck('id')->all())->toBe([$new]);
    Log::shouldHaveReceived('warning')->with('Linked account duplicate spans different users', Mockery::on(fn (array $c) => $c['removed_ids'] === [$old]))->once();
    Log::shouldHaveReceived('warning')->with('Deleted duplicate linked accounts', Mockery::type('array'))->once();
});

it('renames duplicate Party codes, keeps the oldest and echoes each rename', function () {
    $migration = safeMigration('2026_11_28_100000_add_unique_index_to_parties_code');
    $migration->down();
    $parties = Party::factory()->count(3)->create();
    DB::table('parties')->update(['code' => 'DUPE']);

    ob_start();
    $migration->up();
    $output = ob_get_clean();

    $codes = DB::table('parties')->orderBy('id')->pluck('code', 'id');
    expect($codes[$parties[0]->id])->toBe('DUPE')
        ->and($codes->unique())->toHaveCount(3)
        ->and($output)->toContain("Party {$parties[1]->id}: code DUPE renamed to {$codes[$parties[1]->id]}")
        ->and($output)->toContain("Party {$parties[2]->id}: code DUPE renamed to {$codes[$parties[2]->id]}");
});

it('rolls back and reapplies the unique-index migrations', function (string $name) {
    $migration = safeMigration($name);

    $migration->down();
    $migration->up();

    expect(true)->toBeTrue();
})->with([
    '2026_11_28_110000_dedupe_party_members_and_drop_ban_columns',
    '2026_11_28_120000_add_unique_index_to_linked_accounts',
    '2026_11_28_100000_add_unique_index_to_parties_code',
    '2026_11_27_120000_add_unique_index_to_song_ratings_table',
]);
