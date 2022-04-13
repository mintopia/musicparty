<?php

namespace App\Transformers\Api\V1;

use League\Fractal\TransformerAbstract;

class TrackTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
        //
    ];

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(object $track)
    {
        return [
            'spotify_id' => $track->id,
            'name' => $track->name,
            'album' => (object) [
                'spotify_id' => $track->album->id,
                'name' => $track->album->name,
                'image_url' => count($track->album->images) ? $track->album->images[0]->url : null,
            ],
            'artists' => array_map(function ($artist) {
                return (object) [
                    'spotify_id' => $artist->id,
                    'name' => $artist->name,
                ];
            }, $track->artists),
            'length' => $track->duration_ms,
            'votes' => $track->votes,
            'voted' => $track->hasVoted,
        ];
    }
}
