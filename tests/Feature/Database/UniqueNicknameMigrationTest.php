<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const NICKNAME_BASELINE_PATH = 'database/migrations/2026_10_10_100000_create_v3_baseline_schema.php';
const NICKNAME_AUDIT_PATH = 'database/migrations/2026_10_11_200000_retain_admin_audit_entries_after_user_deletion.php';
const NICKNAME_KEY_PATH = 'database/migrations/2026_10_12_100000_add_unique_nickname_key_to_users.php';

beforeEach(function () {
    config([
        'database.default' => 'nickname_test',
        'database.connections.nickname_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::purge('nickname_test');

    Artisan::call('migrate:install');
    foreach ([NICKNAME_BASELINE_PATH, NICKNAME_AUDIT_PATH] as $path) {
        expect(Artisan::call('migrate', ['--path' => $path, '--force' => true]))->toBe(0);
    }
});

function migrateNicknameKey(string $command = 'migrate'): void
{
    expect(Artisan::call($command, ['--path' => NICKNAME_KEY_PATH, '--force' => true]))->toBe(0);
}

it('renames case-insensitive duplicate nicknames keeping the lowest id and audits each rename', function () {
    $kept = DB::table('users')->insertGetId(['nickname' => 'Alex']);
    $renamed = DB::table('users')->insertGetId(['nickname' => 'alex']);
    $third = DB::table('users')->insertGetId(['nickname' => 'ALEX']);
    $unrelated = DB::table('users')->insertGetId(['nickname' => 'Sam']);

    migrateNicknameKey();

    expect(DB::table('users')->where('id', $kept)->value('nickname'))->toBe('Alex')
        ->and(DB::table('users')->where('id', $renamed)->value('nickname'))->toBe('alex 2')
        ->and(DB::table('users')->where('id', $third)->value('nickname'))->toBe('ALEX 3')
        ->and(DB::table('users')->where('id', $unrelated)->value('nickname_key'))->toBe('sam')
        ->and(DB::table('users')->where('id', $kept)->value('nickname_key'))->toBe('alex');

    $unique = collect(Schema::getIndexes('users'))->first(fn (array $index): bool => $index['columns'] === ['nickname_key']);
    assert($unique !== null);
    expect($unique['unique'])->toBeTrue();

    $entry = DB::table('admin_audit_entries')->where('subject_user_id', $renamed)->first();
    assert($entry !== null);
    expect($entry->action)->toBe('user.nickname_deduplicated')
        ->and($entry->admin_id)->toBeNull()
        ->and(json_decode($entry->meta, true))->toBe(['old' => 'alex', 'new' => 'alex 2'])
        ->and(DB::table('admin_audit_entries')->count())->toBe(2);
});

it('does not collide a generated suffix with an existing nickname', function () {
    DB::table('users')->insert(['nickname' => 'Alex']);
    DB::table('users')->insert(['nickname' => 'alex']);
    DB::table('users')->insert(['nickname' => 'Alex 2']);

    migrateNicknameKey();

    $keys = DB::table('users')->pluck('nickname_key');
    expect($keys)->toHaveCount(3)->and($keys->unique())->toHaveCount(3)
        ->and(DB::table('users')->orderBy('id')->pluck('nickname')->all())->toBe(['Alex', 'alex 2', 'Alex 2 2']);
});

it('truncates overlong nicknames and keeps suffixed names within 32 characters', function () {
    $long = str_repeat('x', 40);
    DB::table('users')->insert(['nickname' => $long]);
    DB::table('users')->insert(['nickname' => strtoupper($long)]);

    migrateNicknameKey();

    $names = DB::table('users')->orderBy('id')->pluck('nickname')->all();
    expect($names[0])->toBe(str_repeat('x', 32))
        ->and(mb_strlen($names[1]))->toBe(32)
        ->and($names[1])->toEndWith(' 2');
});

it('drops the column and index on rollback', function () {
    migrateNicknameKey();
    migrateNicknameKey('migrate:rollback');

    expect(Schema::hasColumn('users', 'nickname_key'))->toBeFalse();
});
