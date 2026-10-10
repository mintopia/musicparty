<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;
use Throwable;

class ThrowingRuleMod extends BaseFixtureMod
{
    public function __construct(string $id = 'throwing', private readonly ?Throwable $failure = null)
    {
        parent::__construct($id);
    }

    public function requestRules(): array
    {
        return [new readonly class($this->failure ?? new \RuntimeException('classifier exploded')) implements RequestRule
        {
            public function __construct(private Throwable $failure) {}

            public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
            {
                throw $this->failure;
            }
        }];
    }
}
