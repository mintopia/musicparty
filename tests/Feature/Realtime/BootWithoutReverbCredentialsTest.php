<?php

use Symfony\Component\Process\Process;

it('boots artisan without reverb credentials', function (): void {
    $process = new Process(
        [PHP_BINARY, 'artisan', 'package:discover'],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'BROADCAST_DRIVER' => 'reverb',
            'REVERB_APP_ID' => '',
            'REVERB_APP_KEY' => '',
            'REVERB_APP_SECRET' => '',
        ],
    );
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());
});
