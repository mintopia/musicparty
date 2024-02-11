<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PingResource;

class PingController extends Controller
{
    public function index()
    {
        return new PingResource(null);
    }
}
