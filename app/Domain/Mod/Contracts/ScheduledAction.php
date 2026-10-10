<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SystemRequestSpec;

interface ScheduledAction
{
    public function everySeconds(): int;

    /**
     * @return list<SystemRequestSpec>
     */
    public function run(ModContext $context): array;
}
