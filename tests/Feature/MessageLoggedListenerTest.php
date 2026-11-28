<?php

use App\Listeners\MessageLoggedListener;
use Illuminate\Log\Events\MessageLogged;
use Symfony\Component\Console\Output\BufferedOutput;

it('writes nothing to the console when debug is off', function (): void {
    config(['app.debug' => false]);
    $output = new BufferedOutput;

    (new MessageLoggedListener($output))->handle(new MessageLogged('info', 'quiet message'));

    expect($output->fetch())->toBe('');
});

it('writes the log line to the console when debug is on', function (): void {
    config(['app.debug' => true]);
    $output = new BufferedOutput;

    (new MessageLoggedListener($output))->handle(new MessageLogged('info', 'loud message'));

    expect($output->fetch())->toContain('[info] loud message');
});
