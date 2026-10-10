<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const BASELINE_PATH = 'database/migrations/2026_10_10_100000_create_v3_baseline_schema.php';
const CONSTRAINTS_PATH = 'database/migrations/2026_10_10_110000_dedupe_linked_accounts_and_constrain.php';

beforeEach(function () {
    config([
        'database.default' => 'constraints_test',
        'database.connections.constraints_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge('constraints_test');

    Artisan::call('migrate:install');
    expect(Artisan::call('migrate', ['--path' => BASELINE_PATH, '--force' => true]))->toBe(0);
});

function migrateConstraints(string $command = 'migrate'): void
{
    expect(Artisan::call($command, ['--path' => CONSTRAINTS_PATH, '--force' => true]))->toBe(0);
}

function insertAccount(int $userId, ?int $providerId, ?string $externalId, string $createdAt): int
{
    return DB::table('linked_accounts')->insertGetId([
        'user_id' => $userId,
        'social_provider_id' => $providerId,
        'external_id' => $externalId,
        'created_at' => $createdAt,
    ]);
}

function insertProvider(string $code): int
{
    return DB::table('social_providers')->insertGetId([
        'code' => $code,
        'name' => $code,
        'provider_class' => 'Provider',
    ]);
}

it('merges duplicate linked accounts keeping the oldest and reattaching parties and memberships', function () {
    $providerId = insertProvider('discord');
    $keptUser = DB::table('users')->insertGetId(['nickname' => 'kept']);
    $duplicateUser = DB::table('users')->insertGetId(['nickname' => 'duplicate']);

    $keptAccount = insertAccount($keptUser, $providerId, '42', '2026-01-01 00:00:00');
    $duplicateAccount = insertAccount($duplicateUser, $providerId, '42', '2026-02-01 00:00:00');
    $unrelated = insertAccount($duplicateUser, $providerId, '99', '2026-02-01 00:00:00');

    $ownedParty = DB::table('parties')->insertGetId(['code' => 'OWND', 'name' => 'Owned', 'user_id' => $duplicateUser]);
    $sharedParty = DB::table('parties')->insertGetId(['code' => 'SHRD', 'name' => 'Shared', 'user_id' => $keptUser]);
    $movedMembership = DB::table('party_members')->insertGetId(['user_id' => $duplicateUser, 'party_id' => $ownedParty, 'role' => 'host']);
    DB::table('party_members')->insert(['user_id' => $keptUser, 'party_id' => $sharedParty, 'role' => 'host']);
    DB::table('party_members')->insert(['user_id' => $duplicateUser, 'party_id' => $sharedParty, 'role' => 'guest']);

    migrateConstraints();

    expect(DB::table('linked_accounts')->pluck('id')->all())->toEqualCanonicalizing([$keptAccount, $unrelated]);
    expect(DB::table('linked_accounts')->where('id', $duplicateAccount)->exists())->toBeFalse();
    expect(DB::table('parties')->where('id', $ownedParty)->value('user_id'))->toBe($keptUser);
    expect(DB::table('party_members')->where('id', $movedMembership)->value('user_id'))->toBe($keptUser);
    expect(DB::table('party_members')->where('party_id', $sharedParty)->pluck('user_id')->all())->toBe([$keptUser]);
    expect(DB::table('users')->where('id', $duplicateUser)->exists())->toBeTrue();
});

it('adds the unique index and the foreign key to social_providers', function () {
    migrateConstraints();

    $unique = collect(Schema::getIndexes('linked_accounts'))
        ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['social_provider_id', 'external_id']);
    $foreignKeys = collect(Schema::getForeignKeys('linked_accounts'))
        ->contains(fn (array $fk): bool => $fk['columns'] === ['social_provider_id'] && $fk['foreign_table'] === 'social_providers');

    expect($unique)->toBeTrue();
    expect($foreignKeys)->toBeTrue();

    $providerId = insertProvider('twitch');
    $userId = DB::table('users')->insertGetId(['nickname' => 'u']);
    insertAccount($userId, $providerId, '1', '2026-01-01 00:00:00');

    expect(fn () => insertAccount($userId, $providerId, '1', '2026-01-02 00:00:00'))->toThrow(QueryException::class);
    expect(fn () => insertAccount($userId, 9999, '2', '2026-01-02 00:00:00'))->toThrow(QueryException::class);
});

it('detaches accounts that point at a missing provider before adding the foreign key', function () {
    $userId = DB::table('users')->insertGetId(['nickname' => 'u']);
    $orphan = insertAccount($userId, 9999, '1', '2026-01-01 00:00:00');

    migrateConstraints();

    expect(DB::table('linked_accounts')->where('id', $orphan)->value('social_provider_id'))->toBeNull();
});

it('rolls the constraints back', function () {
    migrateConstraints();
    migrateConstraints('migrate:rollback');

    $unique = collect(Schema::getIndexes('linked_accounts'))
        ->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['social_provider_id', 'external_id']);

    expect($unique)->toBeFalse();
    expect(collect(Schema::getForeignKeys('linked_accounts'))->pluck('foreign_table')->all())->toBe(['users']);
});
