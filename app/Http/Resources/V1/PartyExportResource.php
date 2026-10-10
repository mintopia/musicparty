<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyExportResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return is_array($this->resource) ? $this->resource : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openApiSchema(): array
    {
        $string = ['type' => 'string'];
        $nullableString = ['type' => 'string', 'nullable' => true];
        $integer = ['type' => 'integer'];
        $nullableInteger = ['type' => 'integer', 'nullable' => true];
        $artists = ['type' => 'array', 'items' => $string];
        $list = fn (array $properties): array => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $properties]];

        return [
            'type' => 'object',
            'properties' => [
                'schema_version' => $integer,
                'exported_at' => $string,
                'party' => ['type' => 'object', 'properties' => [
                    'code' => $string,
                    'name' => $nullableString,
                    'state' => $string,
                    'music_provider' => $nullableString,
                    'selection_mode' => $nullableString,
                    'created_at' => $nullableString,
                ]],
                'members' => $list(['id' => $integer, 'nickname' => $string, 'role' => $string]),
                'requests' => $list([
                    'id' => $integer,
                    'provider_track_id' => $string,
                    'title' => $string,
                    'artists' => $artists,
                    'album' => $nullableString,
                    'duration_ms' => $integer,
                    'explicit' => ['type' => 'boolean'],
                    'status' => $string,
                    'requested_by' => $nullableInteger,
                    'requested_at' => $nullableString,
                    'rejection_reason' => $nullableString,
                ]),
                'plays' => $list([
                    'id' => $integer,
                    'request_id' => $nullableInteger,
                    'provider_track_id' => $string,
                    'title' => $string,
                    'artists' => $artists,
                    'album' => $nullableString,
                    'duration_ms' => $integer,
                    'explicit' => ['type' => 'boolean'],
                    'requested_by' => $nullableInteger,
                    'selection_mode' => $nullableString,
                    'selection_score' => $nullableInteger,
                    'played_at' => $string,
                ]),
                'votes' => $list(['request_id' => $integer, 'upvotes' => $integer, 'downvotes' => $integer, 'score' => $integer]),
                'ratings' => $list(['play_id' => $integer, 'likes' => $integer, 'dislikes' => $integer, 'score' => $integer]),
            ],
        ];
    }
}
