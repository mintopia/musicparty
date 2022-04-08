<?php

namespace App\Transformers\Api\V1\Traits;

use App\Models\Party;
use Illuminate\Http\Request;
use League\Fractal\TransformerAbstract;

trait RequestAware
{
    public function __construct(protected Request $request)
    {

    }
}
