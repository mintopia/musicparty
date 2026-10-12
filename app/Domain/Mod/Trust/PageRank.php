<?php

namespace App\Domain\Mod\Trust;

final readonly class PageRank
{
    public const float DAMPING = 0.85;

    public const float TOLERANCE = 1e-6;

    public const int MAX_ITERATIONS = 100;

    /**
     * Personalised PageRank that teleports to the anchor, normalised so the highest score is 1.
     *
     * @param  list<int>  $nodes
     * @param  array<int, array<int, float>>  $edges  from node => (to node => weight)
     * @return array<int, float> node => score
     */
    public function __invoke(array $nodes, array $edges, int $anchor): array
    {
        if (! in_array($anchor, $nodes, true)) {
            return [];
        }

        $rank = array_fill_keys($nodes, 0.0);
        $rank[$anchor] = 1.0;

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $next = $this->step($nodes, $edges, $anchor, $rank);
            $change = 0.0;

            foreach ($nodes as $node) {
                $change += abs($next[$node] - $rank[$node]);
            }

            $rank = $next;

            if ($change < self::TOLERANCE) {
                break;
            }
        }

        return $this->normalise($rank);
    }

    /**
     * @param  list<int>  $nodes
     * @param  array<int, array<int, float>>  $edges
     * @param  array<int, float>  $rank
     * @return array<int, float>
     */
    private function step(array $nodes, array $edges, int $anchor, array $rank): array
    {
        $next = array_fill_keys($nodes, 0.0);
        $next[$anchor] = 1.0 - self::DAMPING;
        $toAnchor = 0.0;

        foreach ($nodes as $node) {
            $outgoing = array_filter($edges[$node] ?? [], fn (float $weight, int $target): bool => $weight > 0 && $target !== $node && isset($next[$target]), ARRAY_FILTER_USE_BOTH);
            $total = array_sum($outgoing);

            if ($total <= 0) {
                $toAnchor += $rank[$node];

                continue;
            }

            foreach ($outgoing as $target => $weight) {
                $next[$target] += self::DAMPING * $rank[$node] * $weight / $total;
            }
        }

        $next[$anchor] += self::DAMPING * $toAnchor;

        return $next;
    }

    /**
     * @param  array<int, float>  $rank
     * @return array<int, float>
     */
    private function normalise(array $rank): array
    {
        $max = $rank === [] ? 0.0 : max($rank);

        if ($max <= 0) {
            return $rank;
        }

        return array_map(fn (float $value): float => $value / $max, $rank);
    }
}
