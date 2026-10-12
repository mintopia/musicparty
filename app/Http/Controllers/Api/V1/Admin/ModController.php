<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Mod\ModRegistry;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AdminModResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModController extends Controller
{
    public function index(ModRegistry $registry): AnonymousResourceCollection
    {
        return AdminModResource::collection(array_values($registry->all()));
    }
}
