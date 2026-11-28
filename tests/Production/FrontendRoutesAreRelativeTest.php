<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

it('generates frontend routes with no absolute host under production settings even when the build env carries an APP_URL', function () {
    $output = sys_get_temp_dir().'/wayfinder-'.uniqid();
    $process = new Process(
        [PHP_BINARY, base_path('artisan'), 'wayfinder:generate', "--path={$output}", '--no-interaction'],
        base_path(),
        [
            'APP_ENV' => 'production',
            'APP_URL' => 'http://localhost',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:dGVzdGluZy1rZXktMzItYnl0ZXMtbG9uZy1hYmNkZWY=',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ],
    );

    try {
        $process->mustRun();
        $files = File::allFiles($output);
        $absolute = collect($files)
            ->filter(fn (SplFileInfo $file): bool => preg_match('~https?://~i', File::get($file->getPathname())) === 1)
            ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
            ->values()
            ->all();

        expect($files)->not->toBeEmpty()
            ->and($absolute)->toBe([]);
    } finally {
        File::deleteDirectory($output);
    }
});
