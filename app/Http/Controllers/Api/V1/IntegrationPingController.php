<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Admin\Models\Integration;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntegrationPingController extends Controller
{
    /**
     * Authenticated liveness check for Integration Tokens holding the `read` ability.
     */
    public function index(Request $request): JsonResponse
    {
        $token = Auth::guard('sanctum')->user();

        return response()->json(['data' => [
            'status' => 'ok',
            'token' => $token instanceof Integration ? $token->name : null,
        ]]);
    }
}
