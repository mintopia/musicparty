<?php

namespace App\Mods\Contracts;

use App\Services\Requests\RequestRule;

interface ContributesRequestRules
{
    /** @return list<RequestRule> */
    public function modRequestRules(): array;

    /** @return list<RequestRule> */
    public function aiRequestRules(): array;
}
