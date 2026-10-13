<?php

namespace App\Mods;

use App\Enums\ScheduleFrequency;
use App\Models\Party;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModScheduler
{
    public function __construct(protected ModRegistry $registry) {}

    public function run(ScheduleFrequency $frequency): void
    {
        foreach ($this->registry->all() as $mod) {
            foreach ($mod->scheduledActions() as $action) {
                if ($action->frequency !== $frequency) {
                    continue;
                }
                $this->runForParties($mod->code(), $action);
            }
        }
    }

    protected function runForParties(string $modCode, ScheduledAction $action): void
    {
        $parties = Party::whereActive(true)->whereHas('mods', function ($query) use ($modCode) {
            $query->where('mods.code', $modCode)->whereNull('mods.removed_at');
        })->get();

        foreach ($parties as $party) {
            try {
                ($action->handler)($party);
            } catch (Throwable $e) {
                Log::error("{$party->id} {$modCode}: Scheduled action {$action->code} failed", ['exception' => $e]);
            }
        }
    }
}
