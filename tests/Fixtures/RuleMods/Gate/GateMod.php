<?php

namespace App\Mods\Gate;

use App\Models\Party;
use App\Mods\Contracts\ContributesRequestRules;
use App\Mods\Contracts\Mod;
use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

class GateMod implements ContributesRequestRules, Mod
{
    public function code(): string
    {
        return 'gate';
    }

    public function name(): string
    {
        return 'Gate';
    }

    public function description(): string
    {
        return 'A request rule fixture mod';
    }

    public function settings(): array
    {
        return [];
    }

    public function extensionPoints(): array
    {
        return ['request-rules'];
    }

    public function modRequestRules(): array
    {
        return [new class implements RequestRule
        {
            public function decide(RequestContext $context, Track $track): RequestDecision
            {
                return RequestDecision::reject('Gate says no');
            }
        }];
    }

    public function aiRequestRules(): array
    {
        return [];
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void {}
}
