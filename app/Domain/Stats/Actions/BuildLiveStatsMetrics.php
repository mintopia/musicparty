<?php

namespace App\Domain\Stats\Actions;

use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyStat;
use App\Models\TrackRequest;

class BuildLiveStatsMetrics
{
    /**
     * @var array<string, list<array{0: int|float, 1: list<string>}>>|null
     */
    private ?array $metrics = null;

    /**
     * @return list<array{0: int|float, 1: list<string>}>
     */
    public function series(string $metric): array
    {
        $this->metrics ??= $this->build();

        return $this->metrics[$metric] ?? [];
    }

    /**
     * @return array<string, list<array{0: int|float, 1: list<string>}>>
     */
    private function build(): array
    {
        $metrics = [
            'parties' => [],
            'party_members' => [],
            'party_queue_length' => [],
            'party_time_played_seconds' => [],
            'party_top_track_plays' => [],
            'party_top_requester_plays' => [],
            'party_most_upvoted_score' => [],
            'party_most_downvoted_score' => [],
        ];

        $stateCounts = Party::query()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        foreach (PartyState::cases() as $state) {
            $metrics['parties'][] = [(int) ($stateCounts[$state->value] ?? 0), [$state->value]];
        }

        $parties = Party::query()
            ->whereIn('state', [PartyState::Live, PartyState::Paused])
            ->orderBy('id')
            ->get(['id', 'code']);

        if ($parties->isEmpty()) {
            return $metrics;
        }

        $ids = $parties->modelKeys();
        $members = PartyMember::query()->whereIn('party_id', $ids)->where('banned', false)
            ->selectRaw('party_id, count(*) as total')->groupBy('party_id')->pluck('total', 'party_id');
        $queued = TrackRequest::query()->whereIn('party_id', $ids)->where('status', RequestStatus::Queued)
            ->selectRaw('party_id, count(*) as total')->groupBy('party_id')->pluck('total', 'party_id');
        $payloads = PartyStat::query()->whereIn('party_id', $ids)->pluck('payload', 'party_id');

        foreach ($parties as $party) {
            $code = (string) $party->code;
            $payload = $payloads[$party->id] ?? ComputePartyStats::empty();

            $metrics['party_members'][] = [(int) ($members[$party->id] ?? 0), [$code]];
            $metrics['party_queue_length'][] = [(int) ($queued[$party->id] ?? 0), [$code]];
            $metrics['party_time_played_seconds'][] = [((int) ($payload['total_time_played_ms'] ?? 0)) / 1000, [$code]];

            foreach (array_values($payload['top_tracks'] ?? []) as $index => $row) {
                $metrics['party_top_track_plays'][] = [(int) $row['plays'], [$code, (string) ($index + 1), $this->trackLabel($row)]];
            }
            foreach (array_values($payload['top_requesters'] ?? []) as $index => $row) {
                $metrics['party_top_requester_plays'][] = [(int) $row['plays'], [$code, (string) ($index + 1), (string) $row['nickname']]];
            }
            foreach (array_values($payload['most_upvoted'] ?? []) as $index => $row) {
                $metrics['party_most_upvoted_score'][] = [(int) $row['score'], [$code, (string) ($index + 1), $this->trackLabel($row)]];
            }
            foreach (array_values($payload['most_downvoted'] ?? []) as $index => $row) {
                $metrics['party_most_downvoted_score'][] = [(int) $row['score'], [$code, (string) ($index + 1), $this->trackLabel($row)]];
            }
        }

        return $metrics;
    }

    /**
     * @param  array{title: string, artists: list<string>}  $row
     */
    private function trackLabel(array $row): string
    {
        $artists = implode(', ', $row['artists']);

        return $artists === '' ? $row['title'] : "{$row['title']} — {$artists}";
    }
}
