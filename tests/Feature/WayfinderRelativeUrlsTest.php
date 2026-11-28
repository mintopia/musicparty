<?php

use App\Providers\TrustedRootUrlServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;

it('generates relative wayfinder urls under the production environment', function () {
    $output = storage_path('framework/testing/wayfinder-'.uniqid());
    File::ensureDirectoryExists($output);

    try {
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'wayfinder:generate', '--skip-actions', '--path='.$output],
            base_path(),
            [
                'APP_ENV' => 'production',
                'APP_URL' => 'https://music.example.com',
                'APP_KEY' => config('app.key'),
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
            ],
        );
        $process->mustRun();

        $generated = collect(File::allFiles($output))
            ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
            ->implode("\n");

        expect($generated)->toContain("url: '/login");
        expect($generated)->not->toContain('http://');
        expect($generated)->not->toContain('https://');
    } finally {
        File::deleteDirectory($output);
    }
});

it('still forces the trusted root url for runtime commands in production', function () {
    config(['app.url' => 'https://music.example.com']);
    app()['env'] = 'production';
    $argv = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'queue:work'];

    try {
        new TrustedRootUrlServiceProvider(app())->boot();
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect(URL::to('/x'))->toBe('https://music.example.com/x');
});
