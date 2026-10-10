<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\DecorationProvider;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use RuntimeException;

class DecoratingMod extends BaseFixtureMod
{
    /**
     * @param  list<mixed>  $decorations
     */
    public function __construct(string $id = 'decorating', private readonly array $decorations = [['badge' => 'HOT', 'icon' => 'flame', 'accent' => 'danger', 'variant' => 'solid']], private readonly bool $failing = false)
    {
        parent::__construct($id);
    }

    public function decorationProviders(): array
    {
        return [new readonly class($this->decorations, $this->failing) implements DecorationProvider
        {
            /**
             * @param  list<mixed>  $decorations
             */
            public function __construct(private array $decorations, private bool $failing) {}

            public function decorate(ModContext $context, TrackRequest|Play $subject): array
            {
                if ($this->failing) {
                    throw new RuntimeException('decoration failed');
                }

                return $this->decorations;
            }
        }];
    }
}
