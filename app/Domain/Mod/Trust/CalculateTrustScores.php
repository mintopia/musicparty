<?php

namespace App\Domain\Mod\Trust;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Mod\Trust\Models\TrustScore;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;

readonly class CalculateTrustScores
{
    public function __construct(
        private TrustGraphBuilder $graph,
        private PageRank $pageRank,
        private RecordPartyLogEntry $record,
    ) {}

    /**
     * Recalculates and stores every Member's trust score, returning member id => score.
     *
     * @return array<int, float>
     */
    public function __invoke(Party $party, TrustSettings $settings): array
    {
        $members = PartyMember::query()->where('party_id', $party->id)->get(['id', 'role']);
        $anchor = $this->anchorId(array_values($members->all()), $settings);

        if ($anchor === null) {
            return [];
        }

        $edges = ($this->graph)($party, now()->subDays($settings->windowDays()));
        $scores = ($this->pageRank)(array_values($members->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()), $edges, $anchor);
        $calculatedAt = now();

        foreach ($scores as $memberId => $score) {
            TrustScore::query()->updateOrCreate(
                ['party_member_id' => $memberId],
                ['party_id' => $party->id, 'score' => $score, 'calculated_at' => $calculatedAt],
            );
        }

        TrustScore::query()->where('party_id', $party->id)->whereNotIn('party_member_id', array_keys($scores))->delete();

        ($this->record)($party, 'mod.trust_scores_calculated', details: [
            'mod' => 'Trust Score',
            'mod_id' => TrustScoreMod::ID,
            'members' => count($scores),
            'anchor_member_id' => $anchor,
            'window_days' => $settings->windowDays(),
        ], systemActor: 'mod:'.TrustScoreMod::ID);

        return $scores;
    }

    /**
     * @param  list<PartyMember>  $members
     */
    private function anchorId(array $members, TrustSettings $settings): ?int
    {
        $configured = $settings->anchorMemberId();

        foreach ($members as $member) {
            if ($configured > 0 && $member->id === $configured) {
                return $member->id;
            }
        }

        foreach ($members as $member) {
            if ($member->role === PartyRole::Host) {
                return $member->id;
            }
        }

        return null;
    }
}
