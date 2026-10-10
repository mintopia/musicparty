<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

it('writes nothing to the console when logging under an artisan command', function () {
    config(['logging.default' => 'single', 'logging.channels.single.path' => storage_path('logs/console-logging-test.log')]);
    Artisan::command('test:log-noise', function () {
        Log::info('should stay out of stdout');
        Log::warning('nor this');
    });

    $output = new BufferedOutput;
    Artisan::call('test:log-noise', [], $output);

    expect($output->fetch())->toBe('');

    @unlink(storage_path('logs/console-logging-test.log'));
});
