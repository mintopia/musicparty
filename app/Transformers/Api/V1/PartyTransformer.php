<?php

namespace App\Transformers\Api\V1;

use App\Models\Artist;
use App\Models\Party;
use App\Transformers\Api\V1\Traits\RequestAware;
use League\Fractal\TransformerAbstract;

class PartyTransformer extends TransformerAbstract
{
    use RequestAware;

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
    public function transform(Party $party)
    {
        return $party->getState($this->request->user());
    }
}
