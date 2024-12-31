<?php

namespace App\Console\Commands;

use App\Models\Party;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class PartyCalculateTrust extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:calculatetrust {party}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate trust scores for a party';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->input->getArgument('party');
        $party = Party::whereCode($code)->first();
        if (!$party) {
            $this->error("Unable to find a party with code {$code}");
            return self::FAILURE;
        }
        $cutoff = new CarbonImmutable('2024-05-01 00:00:00');
        $party->calculateTrustScores($cutoff);

        $io = new SymfonyStyle($this->input, $this->output);

        $scores = [];
        foreach ($party->members()->with('user')->orderBy('trustscore', 'DESC')->get() as $member) {
            $scores[] = [
                $member->user->nickname,
                $member->trustscore,
            ];
        }
        $io->table(['Nickname', 'Score'], $scores);
        return self::SUCCESS;
    }
}
