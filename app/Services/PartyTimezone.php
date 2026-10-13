<?php

namespace App\Services;

use App\Models\Party;
use App\Models\Setting;
use DateTimeZone;

class PartyTimezone
{
    public function instance(): string
    {
        $configured = Setting::fetch('timezone');

        return $this->valid($configured) ? $configured : (string) config('app.timezone');
    }

    public function for(Party $party): string
    {
        return $this->valid($party->timezone) ? $party->timezone : $this->instance();
    }

    public function dateTimeZone(Party $party): DateTimeZone
    {
        return new DateTimeZone($this->for($party));
    }

    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function valid(mixed $value): bool
    {
        return is_string($value) && $value !== '' && in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }
}
