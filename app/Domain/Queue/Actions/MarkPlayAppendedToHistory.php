<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Models\Play;

readonly class MarkPlayAppendedToHistory
{
    public function __invoke(int $playId): void
    {
        Play::query()->whereKey($playId)->update(['history_appended_at' => now()]);
    }
}
