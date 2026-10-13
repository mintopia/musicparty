<?php

use App\Mods\ModRegistry;
use Illuminate\Support\Facades\Schema;

it('has no core table column named for a single mod', function () {
    $registry = new ModRegistry(app_path('Mods'));
    $registry->discover();
    $codes = array_keys($registry->all());
    $modTables = ['mods', 'mod_data', 'mod_settings', 'party_mods', 'party_mod_settings', 'party_mod_setting_events'];

    $offenders = [];
    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        if (in_array($table, $modTables, true)) {
            continue;
        }
        foreach (Schema::getColumnListing($table) as $column) {
            foreach ($codes as $code) {
                if (str_contains($column, $code)) {
                    $offenders[] = "{$table}.{$column}";
                }
            }
        }
    }

    expect($codes)->not->toBeEmpty()->and($offenders)->toBe([]);
});
