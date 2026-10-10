<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

it('writes nothing to stdout when logging under an artisan command', function () {
    Artisan::command('test:log-noise', function () {
        Log::info('should stay out of stdout');
        Log::warning('nor this');
    });

    $output = new BufferedOutput;
    ob_start();
    Artisan::call('test:log-noise', [], $output);
    $stdout = ob_get_clean();

    expect($output->fetch())->toBe('')
        ->and($stdout)->toBe('');
});
