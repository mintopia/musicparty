<?php

namespace App\Console\Commands;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\PartyMods;
use App\Domain\Mod\Trust\CalculateTrustScores;
use App\Domain\Mod\Trust\TrustScoreMod;
use App\Domain\Mod\Trust\TrustSettings;

class PartyCalculateTrust extends PartyCommand
{
    protected $signature = 'party:calculatetrust {code : The Party code}';

    protected $description = 'Recalculate the Trust Score of every Member of a Party now';

    protected function perform(): int
    {
        $party = $this->resolveParty((string) $this->argument('code'));

        if ($party === null) {
            return self::FAILURE;
        }

        $context = app(PartyMods::class)->contextFor($party, TrustScoreMod::ID);
        $scores = app(CalculateTrustScores::class)($party, new TrustSettings($context->settings ?? []));

        $members = PartyMember::query()->whereIn('id', array_keys($scores))->with('user')->get()->keyBy('id');

        $rows = collect($scores)
            ->sortDesc()
            ->map(fn (float $score, int $memberId): array => [$members->get($memberId)?->holder()->nickname ?? (string) $memberId, number_format($score, 4)])
            ->values()
            ->all();

        $this->table(['Nickname', 'Score'], $rows);
        $this->info("Calculated trust for {$party->code}.");

        return self::SUCCESS;
    }
}
