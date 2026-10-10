<?php

use App\Domain\Mod\Jobs\ReviewRequestWithAi;
use App\Domain\Mod\Jobs\RunModScheduledActions;
use App\Domain\Music\Jobs\AppendToHistoryPlaylist;
use App\Domain\Playback\Jobs\CheckSoloistHealth;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\Jobs\ProcessPlayerFrame;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Playback\Jobs\TickParty;
use App\Domain\Playback\Jobs\TickPlayback;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

it('routes each job to its queue', function (object $job, string $queue) {
    expect($job->queue)->toBe($queue);
})->with([
    'frame' => fn () => [new ProcessPlayerFrame('ABC123'), 'player'],
    'start' => fn () => [new StartPlayback('ABC123'), 'player'],
    'tick' => fn () => [new TickPlayback, 'player'],
    'party tick' => fn () => [new TickParty('ABC123'), 'ticks'],
    'health' => fn () => [new CheckSoloistHealth, 'player'],
    'poll' => fn () => [new PollPlayback('ABC123'), 'polling'],
    'broadcast' => fn () => [new BroadcastPartyQueue('ABC123'), 'broadcast'],
    'ai' => fn () => [new ReviewRequestWithAi(1, 1), 'mods-ai'],
    'mods' => fn () => [new RunModScheduledActions, 'default'],
    'history' => fn () => [new AppendToHistoryPlaylist(1, 'track', 'spotify'), 'default'],
]);

it('defines one supervisor per queue', function () {
    $queues = collect(config('horizon.defaults'))->flatMap(fn ($s) => $s['queue'])->sort()->values()->all();

    expect($queues)->toBe(['broadcast', 'default', 'mods-ai', 'player', 'polling', 'ticks']);
});

it('sends every broadcast event to the broadcast queue', function () {
    $events = collect(glob(app_path('Domain/{Queue,Stats,Theming}/Broadcast/*Event.php'), GLOB_BRACE))
        ->map(fn (string $file) => 'App\\Domain\\'.basename(dirname($file, 2)).'\\Broadcast\\'.basename($file, '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, ShouldBroadcast::class));

    expect($events)->not->toBeEmpty();

    foreach ($events as $class) {
        $event = new ReflectionClass($class)->newInstanceWithoutConstructor();

        expect($event->broadcastQueue)->toBe('broadcast', $class);
    }
});
