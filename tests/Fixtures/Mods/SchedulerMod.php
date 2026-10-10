<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\ScheduledAction;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SystemRequestSpec;

class SchedulerMod extends BaseFixtureMod
{
    public int $runs = 0;

    public function __construct(string $id = 'scheduler', private readonly string $trackId = 'track-3', private readonly bool $bypassRules = false, private readonly int $every = 60, private readonly bool $failing = false)
    {
        parent::__construct($id);
    }

    public function scheduledActions(): array
    {
        return [new readonly class($this, $this->trackId, $this->bypassRules, $this->every, $this->failing) implements ScheduledAction
        {
            public function __construct(private SchedulerMod $mod, private string $trackId, private bool $bypassRules, private int $every, private bool $failing) {}

            public function everySeconds(): int
            {
                return $this->every;
            }

            public function run(ModContext $context): array
            {
                $this->mod->runs++;

                if ($this->failing) {
                    throw new \RuntimeException('schedule exploded');
                }

                return [new SystemRequestSpec($this->trackId, $this->bypassRules)];
            }
        }];
    }
}
