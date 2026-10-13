<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function themeTokensMigration(): object
{
    return require database_path('migrations/2026_10_13_160000_convert_theme_colours_to_tokens.php');
}

it('turns primary and nav_background into tokens and restores them on down', function () {
    $migration = themeTokensMigration();
    $migration->down();
    expect(Schema::hasColumn('themes', 'primary'))->toBeTrue();

    DB::table('themes')->insert([
        'name' => 'Legacy', 'code' => 'legacy', 'primary' => '#112233', 'nav_background' => '#445566',
        'css' => 'a{}', 'dark_mode' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    expect(Schema::hasColumn('themes', 'primary'))->toBeFalse()
        ->and(Schema::hasColumn('themes', 'nav_background'))->toBeFalse();
    $row = DB::table('themes')->where('code', 'legacy')->first();
    expect(json_decode($row->tokens, true))->toBe(['primary' => '#112233', 'nav-background' => '#445566'])
        ->and($row->css)->toBe('a{}')
        ->and((bool) $row->dark_mode)->toBeTrue();

    $migration->down();

    $row = DB::table('themes')->where('code', 'legacy')->first();
    expect($row->primary)->toBe('#112233')->and($row->nav_background)->toBe('#445566');

    $migration->up();
});
