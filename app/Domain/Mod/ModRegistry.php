<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Contracts\Mod;
use InvalidArgumentException;

class ModRegistry
{
    /** @var array<string, Mod> */
    private array $mods = [];

    public function register(Mod $mod): void
    {
        if (isset($this->mods[$mod->id()])) {
            throw new InvalidArgumentException("Mod [{$mod->id()}] is already registered.");
        }

        $this->mods[$mod->id()] = $mod;
    }

    /**
     * @return array<string, Mod>
     */
    public function all(): array
    {
        return $this->mods;
    }

    public function find(string $id): ?Mod
    {
        return $this->mods[$id] ?? null;
    }
}
