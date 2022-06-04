<?php

namespace App\Transformers\Api\V1;

use App\Models\UpcomingSong;
use App\Transformers\Api\V1\Traits\RequestAware;
use League\Fractal\TransformerAbstract;

class UpcomingSongTransformer extends TransformerAbstract
{
    public function __construct(protected array $votes = [])
    {

    }

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
    public function transform(UpcomingSong $song)
    {
        $data = [
            'id' => $song->id,
        ];
        $data = array_merge($data, $song->toApi());
        $data['voted'] = in_array($song->id, $this->votes);
        $data['created_at'] = $song->created_at->toIso8601String();
        return $data;
    }
}
