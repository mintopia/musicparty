<?php

namespace App\Services\Dashboard;

class AdminDashboard
{
    public function __construct(
        private readonly SystemHealth $health,
        private readonly LiveParties $liveParties,
        private readonly AdminAttention $attention,
        private readonly AdminActivity $activity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $parties = $this->liveParties->all();

        return [
            'generatedAt' => now()->toIso8601String(),
            'liveParties' => $parties->map(fn (array $row): array => $row['party'])->values()->all(),
            'quietFor' => $parties->isEmpty() ? $this->activity->quietFor() : null,
            'attention' => $this->attention->items(array_values($parties->pluck('model')->all())),
            'health' => $this->health->checks(),
            'activity' => $this->activity->activity(),
            'links' => [
                'pulse' => route('pulse'),
                'horizon' => route('horizon.index'),
                'createParty' => route('parties.create'),
            ],
        ];
    }
}
