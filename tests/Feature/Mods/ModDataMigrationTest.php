<?php

use App\Models\ModData;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\ModDataStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function modDataMigration(): object
{
    return require database_path('migrations/2026_10_13_200000_move_mod_columns_to_mod_data.php');
}

function seedLegacyColumns(): array
{
    $party = Party::factory()->create();
    $plain = Party::factory()->create();
    $user = User::factory()->create();
    $member = PartyMember::factory()->create(['party_id' => $party->id]);
    $idleMember = PartyMember::factory()->create(['party_id' => $party->id]);
    $request = UpcomingSong::factory()->for($party)->create();
    $bare = UpcomingSong::factory()->for($party)->create();

    DB::table('upcoming_songs')->where('id', $request->id)->update(['fallback_override' => 'Wham', 'css_classes' => 'a b']);
    DB::table('parties')->where('id', $party->id)->update(['trustscore' => true, 'trusted_user_id' => $user->id]);
    DB::table('party_members')->where('id', $member->id)->update(['trustscore' => 0.75]);

    return compact('party', 'plain', 'user', 'member', 'idleMember', 'request', 'bare');
}

it('moves fallback override, css classes and trust data into mod_data and drops the columns', function () {
    $migration = modDataMigration();
    $migration->down();
    $rows = seedLegacyColumns();

    $migration->up();

    $store = app(ModDataStore::class);
    expect(Schema::hasColumn('upcoming_songs', 'fallback_override'))->toBeFalse()
        ->and(Schema::hasColumn('upcoming_songs', 'css_classes'))->toBeFalse()
        ->and(Schema::hasColumn('parties', 'trustscore'))->toBeFalse()
        ->and(Schema::hasColumn('parties', 'trusted_user_id'))->toBeFalse()
        ->and(Schema::hasColumn('party_members', 'trustscore'))->toBeFalse()
        ->and($store->all($rows['request'], 'whamageddon'))->toEqual(['fallback_override' => 'Wham', 'css_classes' => 'a b'])
        ->and($store->all($rows['bare'], 'whamageddon'))->toBe([])
        ->and($store->get($rows['party'], 'trust', 'trustscore'))->toEqual(true)
        ->and($store->get($rows['party'], 'trust', 'trusted_user_id'))->toBe($rows['user']->id)
        ->and($store->all($rows['plain'], 'trust'))->toBe([])
        ->and($store->get($rows['member'], 'trust', 'trustscore'))->toBe(0.75)
        ->and($store->all($rows['idleMember'], 'trust'))->toBe([])
        ->and(ModData::count())->toBe(5);
});

it('restores the columns and drops mod_data on down', function () {
    $migration = modDataMigration();

    $migration->down();

    expect(Schema::hasTable('mod_data'))->toBeFalse()
        ->and(Schema::hasColumns('upcoming_songs', ['fallback_override', 'css_classes']))->toBeTrue()
        ->and(Schema::hasColumns('parties', ['trustscore', 'trusted_user_id']))->toBeTrue()
        ->and(Schema::hasColumn('party_members', 'trustscore'))->toBeTrue();

    $migration->up();
});

it('stores owners by morph class with unique mod keys', function () {
    $party = Party::factory()->create();
    $store = app(ModDataStore::class);

    $store->set($party, 'trust', 'k', ['a' => 1]);
    $store->set($party, 'trust', 'k', 2);
    $store->set($party, 'other', 'k', 'x');

    expect($store->get($party, 'trust', 'k'))->toBe(2)
        ->and($store->get($party, 'other', 'k'))->toBe('x')
        ->and($store->get($party, 'trust', 'missing', 'dflt'))->toBe('dflt')
        ->and(ModData::count())->toBe(2)
        ->and(ModData::first()->owner_type)->toBe($party->getMorphClass());

    $store->forget($party, 'trust', 'k');

    expect($store->all($party, 'trust'))->toBe([]);
});
