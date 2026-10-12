<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MirrorsLogToConsole;
use App\Domain\Party\Models\Party;
use Illuminate\Console\Command;

abstract class PartyCommand extends Command
{
    use MirrorsLogToConsole;

    public function handle(): int
    {
        return $this->mirroringLog(fn (): int => $this->perform());
    }

    abstract protected function perform(): int;

    protected function resolveParty(string $code): ?Party
    {
        $party = Party::findByCode($code);

        if ($party === null) {
            $this->error("No Party found with code {$code}.");
        }

        return $party;
    }
}
