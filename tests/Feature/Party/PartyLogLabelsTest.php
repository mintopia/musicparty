<?php

use Illuminate\Support\Facades\File;

/**
 * @return array<int, string>
 */
function backendPartyLogKeys(): array
{
    $keys = [];
    foreach (File::allFiles(app_path()) as $file) {
        $source = $file->getContents();
        if (! str_contains($source, 'RecordPartyLogEntry')) {
            continue;
        }
        preg_match_all("/'((?:party|member|blocklist|mod|queue|request|fallback|player|player_token|playlist|act_as_host)\.[a-z_]+)'/", $source, $matches);
        array_push($keys, ...$matches[1]);
    }

    return array_values(array_unique($keys));
}

it('has a Party Log label for every event key the backend writes', function () {
    $labels = json_decode((string) file_get_contents(resource_path('js/lib/partyLogLabels.json')), true, flags: JSON_THROW_ON_ERROR);
    $keys = backendPartyLogKeys();

    expect($keys)->not->toBeEmpty();
    expect(array_values(array_diff($keys, array_keys($labels))))->toBe([]);
});

it('has no label for an event key the backend no longer writes', function () {
    $labels = json_decode((string) file_get_contents(resource_path('js/lib/partyLogLabels.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(array_values(array_diff(array_keys($labels), backendPartyLogKeys())))->toBe([]);
});
