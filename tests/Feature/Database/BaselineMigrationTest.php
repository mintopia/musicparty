<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Schema\V2Schema;
use Tests\Fixtures\Schema\V3PreSquashSchema;

beforeEach(function () {
    config([
        'database.default' => 'baseline_test',
        'database.connections.baseline_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge('baseline_test');

    Artisan::call('migrate:install');
});

/**
 * @return array<string, array{columns: list<array<string, mixed>>, indexes: list<array<string, mixed>>}>
 */
function describeSchema(): array
{
    $schema = [];

    foreach (Schema::getTables() as $table) {
        $name = $table['name'];

        if (in_array($name, ['migrations', 'sqlite_sequence'], true)) {
            continue;
        }

        $columns = array_map(
            fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type_name'],
                'nullable' => $column['nullable'],
                'default' => $column['default'],
            ],
            Schema::getColumns($name),
        );
        $indexes = array_map(
            fn (array $index): array => [
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'],
            ],
            Schema::getIndexes($name),
        );

        usort($columns, fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        usort($indexes, fn (array $a, array $b): int => json_encode($a) <=> json_encode($b));

        $schema[$name] = ['columns' => $columns, 'indexes' => $indexes];
    }

    ksort($schema);

    return $schema;
}

function runBaseline(string $command = 'migrate'): void
{
    expect(Artisan::call($command, ['--path' => 'database/migrations/2026_10_10_100000_create_v3_baseline_schema.php', '--force' => true]))->toBe(0);
}

it('builds the v3 schema from an empty database', function () {
    runBaseline();

    expect(array_keys(describeSchema()))->toEqualCanonicalizing([
        'admin_audit_entries', 'admin_host_sessions', 'blocklist_entries', 'failed_jobs', 'instance_themes',
        'integration_tokens', 'linked_accounts', 'parties', 'party_log_entries', 'party_members', 'party_mods',
        'party_stats', 'personal_access_tokens', 'plays', 'provider_settings', 'ratings', 'request_votes',
        'role_user', 'roles', 'settings', 'social_providers', 'track_requests', 'users',
    ]);
    expect(Schema::hasColumns('parties', ['code', 'state', 'selection_mode', 'theme']))->toBeTrue();
    expect(DB::table('migrations')->pluck('migration')->all())->toBe(['2026_10_10_100000_create_v3_baseline_schema']);
});

it('upgrades a v2 database to the v3 schema and drops the v2-only tables', function () {
    runBaseline();
    $expected = describeSchema();
    runBaseline('migrate:rollback');

    V2Schema::create();
    DB::table('users')->insert(['nickname' => 'v2 user']);
    expect(Schema::hasTable('songs'))->toBeTrue();

    runBaseline();

    foreach (V2Schema::V2_ONLY_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse("{$table} should be gone");
    }
    expect(describeSchema())->toEqual($expected);
    expect(DB::table('users')->count())->toBe(0);
});

it('upgrades a partial v3 database that never reached the final pre-squash migration', function () {
    runBaseline();
    $expected = describeSchema();
    runBaseline('migrate:rollback');

    Schema::create('play_ratings', fn ($table) => $table->id());
    Schema::create('users', fn ($table) => $table->id());

    runBaseline();

    expect(Schema::hasTable('play_ratings'))->toBeFalse();
    expect(describeSchema())->toEqual($expected);
});

it('keeps every row of a database already at the final pre-squash v3 migration', function () {
    V3PreSquashSchema::create();
    $schemaBefore = describeSchema();

    $userId = DB::table('users')->insertGetId(['nickname' => 'host']);
    $partyId = DB::table('parties')->insertGetId(['code' => 'ABCD', 'name' => 'Party', 'user_id' => $userId]);

    runBaseline();

    expect(DB::table('users')->where('id', $userId)->value('nickname'))->toBe('host');
    expect(DB::table('parties')->where('id', $partyId)->value('code'))->toBe('ABCD');
    expect(describeSchema())->toEqual($schemaBefore);
    expect(DB::table('migrations')->where('migration', '2026_10_10_100000_create_v3_baseline_schema')->exists())->toBeTrue();
});

it('round-trips through rollback and migrate', function () {
    runBaseline();
    $expected = describeSchema();

    runBaseline('migrate:rollback');
    expect(describeSchema())->toBe([]);

    runBaseline();
    expect(describeSchema())->toEqual($expected);
});

it('rebuilds the schema when a pre-squash v3 database is rolled back and migrated again', function () {
    V3PreSquashSchema::create();
    $schemaBefore = describeSchema();
    runBaseline();

    runBaseline('migrate:rollback');
    expect(Schema::hasTable('parties'))->toBeFalse();

    runBaseline();
    expect(describeSchema())->toEqual($schemaBefore);
});

it('matches the pre-squash v3 schema fixture column for column', function () {
    runBaseline();
    $fromEmpty = describeSchema();
    runBaseline('migrate:rollback');

    V3PreSquashSchema::create();

    expect(describeSchema())->toEqual($fromEmpty);
});
