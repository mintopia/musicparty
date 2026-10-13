<?php

namespace App\Mods\Trust;

class TrustCalculator
{
    public const DAMPING = 0.85;

    public const TOLERANCE = 1e-6;

    public const MAX_ITERATIONS = 100;

    public const MIN_MEMBERS = 3;

    public const MIN_ENDORSEMENTS = 5;

    /**
     * @param  list<int>  $members  user ids of the Party's members
     * @param  array<int, array<int, int>>  $endorsements  voter user id => [request owner user id => positive vote + rating count]
     * @return array<int, float> every member user id => score in [0,1]
     */
    public function calculate(array $members, int $trustedUserId, array $endorsements): array
    {
        $zeros = array_fill_keys($members, 0.0);
        $isMember = array_fill_keys($members, true);
        $edges = [];
        $total = 0;

        foreach ($endorsements as $voter => $owners) {
            foreach ($owners as $owner => $count) {
                if ($count > 0 && $voter !== $owner && isset($isMember[$voter], $isMember[$owner])) {
                    $edges[$voter][$owner] = $count;
                    $total += $count;
                }
            }
        }

        if (! isset($isMember[$trustedUserId]) || count($members) < self::MIN_MEMBERS || $total < self::MIN_ENDORSEMENTS) {
            return $zeros;
        }

        $weights = array_map(static fn (array $row): array => array_map(static fn (int $count): float => $count / array_sum($row), $row), $edges);
        $teleport = array_replace($zeros, [$trustedUserId => 1.0]);
        $trust = $teleport;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $next = array_map(static fn (float $p): float => (1 - self::DAMPING) * $p, $teleport);
            $dangling = 0.0;

            foreach ($trust as $voter => $mass) {
                if (! isset($weights[$voter])) {
                    $dangling += $mass;

                    continue;
                }

                foreach ($weights[$voter] as $owner => $weight) {
                    $next[$owner] += self::DAMPING * $mass * $weight;
                }
            }

            $next[$trustedUserId] += self::DAMPING * $dangling;
            $change = array_sum(array_map(static fn (float $a, float $b): float => abs($a - $b), $trust, $next));
            $trust = $next;

            if ($change < self::TOLERANCE) {
                break;
            }
        }

        $max = max($trust);

        return $max > 0 ? array_map(static fn (float $score): float => min(1.0, max(0.0, $score / $max)), $trust) : $zeros;
    }
}
