<?php

use App\Domain\Playback\Jobs\CheckSoloistHealth;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Jobs\BroadcastPartyQueue;
use App\Jobs\ProcessPlayerFrame;
use App\Jobs\ReviewRequestWithAi;
use App\Jobs\RunModScheduledActions;
use App\Jobs\StartPlayback;
use App\Jobs\TickPlayback;

it('routes each job to its queue', function (object $job, string $queue) {
    expect($job->queue)->toBe($queue);
})->with([
    'frame' => fn () => [new ProcessPlayerFrame('ABC123', []), 'player'],
    'start' => fn () => [new StartPlayback('ABC123'), 'player'],
    'tick' => fn () => [new TickPlayback, 'player'],
    'health' => fn () => [new CheckSoloistHealth, 'player'],
    'poll' => fn () => [new PollPlayback('ABC123'), 'polling'],
    'broadcast' => fn () => [new BroadcastPartyQueue('ABC123'), 'broadcast'],
    'ai' => fn () => [new ReviewRequestWithAi(1, 1), 'mods-ai'],
    'mods' => fn () => [new RunModScheduledActions, 'default'],
]);

it('defines one supervisor per queue', function () {
    $queues = collect(config('horizon.defaults'))->flatMap(fn ($s) => $s['queue'])->sort()->values()->all();

    expect($queues)->toBe(['broadcast', 'default', 'mods-ai', 'player', 'polling']);
});
