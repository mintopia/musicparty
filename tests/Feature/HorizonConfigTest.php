<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\Support\DeployConfig;

it('retries the broadcast and default supervisors with backoff', function (string $supervisor) {
    expect(config("horizon.defaults.{$supervisor}.tries"))->toBe(3)
        ->and(config("horizon.defaults.{$supervisor}.backoff"))->toBe([1, 5, 15]);
})->with(['supervisor-broadcast', 'supervisor-default']);

it('keeps the player and ticks supervisors at one try', function (string $supervisor) {
    expect(config("horizon.defaults.{$supervisor}.tries"))->toBe(1);
})->with(['supervisor-player', 'supervisor-ticks']);

it('resolves supervisors for any environment through the wildcard block', function (string $environment) {
    $supervisors = collect(config('horizon.environments'))
        ->first(fn (array $_, string $name): bool => Str::is($name, $environment));

    expect(array_keys($supervisors))->toEqual(array_keys(config('horizon.defaults')));
})->with(['staging', 'qa']);

it('schedules horizon:snapshot every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'horizon:snapshot'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});

it('keeps every supervisor timeout below the Horizon stop grace period', function () {
    $grace = (int) DeployConfig::compose('example/docker-compose.yml')->service('horizon')['stop_grace_period'];

    foreach (config('horizon.defaults') as $supervisor => $options) {
        expect($options['timeout'])->toBeLessThan($grace, $supervisor);
    }
});
