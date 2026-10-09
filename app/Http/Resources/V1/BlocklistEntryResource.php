<?php

namespace App\Http\Resources\V1;

use App\Models\BlocklistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlocklistEntry
 */
class BlocklistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_type' => $this->match_type->value,
            'value' => $this->value,
            'is_regex' => $this->is_regex,
            'is_enabled' => $this->is_enabled,
            'notes' => $this->notes,
        ];
    }
}
