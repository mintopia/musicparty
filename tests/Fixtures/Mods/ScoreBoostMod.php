<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\ScoreModifier;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Queue\Models\TrackRequest;
use RuntimeException;

class ScoreBoostMod extends BaseFixtureMod
{
    public function __construct(string $id = 'score-boost', private readonly ?int $memberId = null, private readonly int $boost = 3, private readonly bool $failing = false)
    {
        parent::__construct($id);
    }

    public function scoreModifiers(): array
    {
        return [new readonly class($this->memberId, $this->boost, $this->failing) implements ScoreModifier
        {
            public function __construct(private ?int $memberId, private int $boost, private bool $failing) {}

            public function adjustment(ModContext $context, TrackRequest $request): int
            {
                if ($this->failing) {
                    throw new RuntimeException('score failed');
                }

                return $this->memberId === null || $request->party_member_id === $this->memberId ? $this->boost : 0;
            }
        }];
    }
}
