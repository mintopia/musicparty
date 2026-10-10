<?php

namespace App\Http\Resources\V1;

use App\Models\PartyLogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartyLogEntry
 */
class PartyLogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'subject' => $this->subject,
            'details' => $this->details,
            'actor' => $this->user->nickname ?? $this->system_actor,
            'actor_kind' => $this->user_id === null ? 'system' : 'member',
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
