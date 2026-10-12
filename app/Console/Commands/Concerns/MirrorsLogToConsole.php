<?php

namespace App\Console\Commands\Concerns;

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
        $active = true;
        $minimum = $this->mirroredLogLevel();

        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$active, $minimum): void {
            if ($active && Level::fromName($event->level)->value >= $minimum->value) {
                $this->output->writeln("[{$event->level}] {$event->message}");
            }
        });

        try {
            return $callback();
        } finally {
            $active = false;
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
