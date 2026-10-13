<?php

namespace App\Mods\Contracts;

use App\Mods\SettingDefinition;
use App\Models\Party;

interface Mod
{
    public function code(): string;

    public function name(): string;

    public function description(): string;

    /** @return list<SettingDefinition> */
    public function settings(): array;

    /** @return list<string> */
    public function extensionPoints(): array;

    public function onEnable(Party $party): void;

    public function onDisable(Party $party): void;
}
