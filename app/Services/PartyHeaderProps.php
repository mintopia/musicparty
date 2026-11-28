<?php

namespace App\Services;

use App\Models\Party;
use App\Models\User;

class PartyHeaderProps
{
    /**
     * @return array{code: string, name: string, downvotes: bool, weighted: bool, canManage: bool, canModerate: bool}
     */
    public function for(Party $party, ?User $user): array
    {
        return [
            'code' => $party->code,
            'name' => $party->name,
            'downvotes' => (bool) $party->downvotes,
            'weighted' => (bool) $party->weighted,
            'canManage' => $party->canBeManagedBy($user),
            'canModerate' => $user?->can('moderate', $party) ?? false,
        ];
    }
}
