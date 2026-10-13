<?php

use App\Models\Party;
use App\Models\User;
use Symfony\Component\Finder\Finder;

function phpFilesUnder(array $dirs): Finder
{
    return Finder::create()->files()->name('*.php')->in(array_map(fn (string $dir) => dirname(__DIR__, 2).'/'.$dir, $dirs));
}

arch('the Spotify SDK is only used by the Spotify Music Provider and the gateway')
    ->expect('SpotifyWebAPI')
    ->toOnlyBeUsedIn([
        'App\Services\Music\Spotify',
        'App\Services\Spotify',
    ]);

arch('Party and User hold no Spotify client code')
    ->expect([Party::class, User::class])
    ->not->toUse(['SpotifyWebAPI', 'App\Services\Spotify']);

it('keeps the spotify:track: literal inside the Spotify Music Provider and the gateway', function () {
    $root = dirname(__DIR__, 2).'/app/Services/';

    $offenders = [];
    foreach (phpFilesUnder(['app']) as $file) {
        $path = $file->getPathname();
        if (str_starts_with($path, $root.'Music/Spotify/') || str_starts_with($path, $root.'Spotify/')) {
            continue;
        }
        if (str_contains($file->getContents(), 'spotify:track:')) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('has no getSpotifyApi anywhere in the application', function () {
    $offenders = [];
    foreach (phpFilesUnder(['app', 'database', 'routes', 'resources', 'tests']) as $file) {
        if ($file->getPathname() !== __FILE__ && str_contains($file->getContents(), 'getSpotifyApi')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
