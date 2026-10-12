<?php

namespace App\Domain\Mod\Whamageddon;

use Carbon\CarbonImmutable;

final readonly class WhamageddonSettings
{
    public const string DEFAULT_TRACK = '2FRnf9qhLbvw8fu4IBXx78';

    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values) {}

    public function trackId(): string
    {
        $raw = $this->string('track', self::DEFAULT_TRACK);

        if (preg_match('/track[\/:]([A-Za-z0-9]+)/', $raw, $matches) === 1) {
            return $matches[1];
        }

        return $raw;
    }

    public function label(): string
    {
        return $this->string('label', 'Whamageddon');
    }

    public function hourlyBonus(): int
    {
        return $this->int('hourly_bonus', 1);
    }

    public function readdIntervalHours(): int
    {
        return $this->int('readd_interval_hours', 2);
    }

    public function now(): CarbonImmutable
    {
        return now()->toImmutable()->setTimezone(config('app.timezone'));
    }

    public function isQuiet(CarbonImmutable $at): bool
    {
        $start = $this->int('quiet_start_hour', 2);
        $end = $this->int('quiet_end_hour', 10);
        $hour = $at->hour;

        return $start <= $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
    }

    /**
     * The moment until which the Request should be held back, or null when it may be selected.
     */
    public function holdUntil(CarbonImmutable $at): ?CarbonImmutable
    {
        if (! $this->isQuiet($at) && $at->hour < $this->int('hold_from_hour', 23)) {
            return null;
        }

        $end = $at->setTime($this->int('quiet_end_hour', 10), 0);

        return $end->greaterThan($at) ? $end : $end->addDay();
    }

    private function string(string $key, string $default): string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
