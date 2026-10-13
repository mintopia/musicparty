<?php

namespace App\Mods\Contracts;

use App\Models\Party;
use App\Mods\ScheduledAction;
use App\Mods\SettingDefinition;

interface Mod
{
    public function code(): string;

    public function name(): string;

    public function description(): string;

    /** @return list<SettingDefinition> */
    public function settings(): array;

    /** @return list<string> */
    public function extensionPoints(): array;

    /** @return list<ScheduledAction> */
    public function scheduledActions(): array;

    public function onEnable(Party $party): void;

    public function onDisable(Party $party): void;
}
