<?php

namespace App\Console\Commands\Concerns;

use ArrayObject;
use Closure;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Monolog\Level;
use Symfony\Component\Console\Output\OutputInterface;

trait MirrorsLogToConsole
{
    /**
     * @param  Closure(): int  $callback
     */
    protected function mirroringLog(Closure $callback): int
    {
        $state = new ArrayObject(['active' => true]);
        $minimum = $this->mirroredLogLevel();

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($state, $minimum): void {
            if ($state['active'] === true && Level::fromName($event->level)->value >= $minimum->value) {
                $this->output->writeln("[{$event->level}] {$event->message}");
            }
        });

        try {
            return $callback();
        } finally {
            $state['active'] = false;
        }
    }

    private function mirroredLogLevel(): Level
    {
        return match (true) {
            $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Debug,
            $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE => Level::Info,
            default => Level::Warning,
        };
    }
}
