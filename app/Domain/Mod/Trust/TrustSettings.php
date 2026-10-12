<?php

namespace App\Domain\Mod\Trust;

final readonly class TrustSettings
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values) {}

    public function anchorMemberId(): int
    {
        return $this->int('anchor_member_id', 0);
    }

    public function windowDays(): int
    {
        return max($this->int('window_days', 7), 1);
    }

    public function weightingEnabled(): bool
    {
        return filter_var($this->values['weighting'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function maxBonus(): int
    {
        return min(max($this->int('max_bonus', 2), 0), 10);
    }

    private function int(string $key, int $default): int
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
