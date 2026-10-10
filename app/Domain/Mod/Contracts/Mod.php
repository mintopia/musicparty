<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SettingDefinition;

interface Mod
{
    public function id(): string;

    public function name(): string;

    public function description(): string;

    /**
     * @return list<SettingDefinition>
     */
    public function settings(): array;

    /**
     * @return list<RequestRule>
     */
    public function requestRules(): array;

    /**
     * @return list<ScoreModifier>
     */
    public function scoreModifiers(): array;

    /**
     * @return list<ScheduledAction>
     */
    public function scheduledActions(): array;

    /**
     * Event class name => listener callable taking (event, ModContext).
     *
     * @return array<class-string, callable(object, ModContext): void>
     */
    public function listeners(): array;
}
