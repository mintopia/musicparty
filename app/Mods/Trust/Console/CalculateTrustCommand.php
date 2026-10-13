<?php

namespace App\Mods\Trust\Console;

use App\Models\Party;
use App\Models\User;
use App\Mods\Trust\TrustScores;
use Illuminate\Console\Command;

class CalculateTrustCommand extends Command
{
    protected $signature = 'party:calculatetrust {party}';

    protected $description = 'Calculate trust scores for a party';

    public function handle(TrustScores $trust): int
    {
        $code = (string) $this->argument('party');
        $party = Party::whereCode($code)->first();
        if ($party === null) {
            $this->error("Unable to find a party with code {$code}");

            return self::FAILURE;
        }

        $scores = $trust->recalculate($party);
        $nicknames = User::whereIn('id', array_keys($scores))->pluck('nickname', 'id');
        arsort($scores);

        $this->table(
            ['Nickname', 'Score'],
            collect($scores)->map(fn (float $score, int $userId): array => [$nicknames[$userId] ?? $userId, $score])->values()->all(),
        );

        return self::SUCCESS;
    }
}
