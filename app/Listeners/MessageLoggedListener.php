<?php

namespace App\Listeners;

use Illuminate\Log\Events\MessageLogged;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MessageLoggedListener
{
    public function __construct(private readonly ?OutputInterface $output = null) {}

    /**
     * Handle the event.
     */
    public function handle(MessageLogged $event): void
    {
        if (config('app.debug') && app()->runningInConsole()) {
            $output = $this->output ?? new ConsoleOutput;
            $output->writeln("[{$event->level}] {$event->message}");
        }
    }
}
