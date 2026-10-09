<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationPingController extends Controller
{
    /**
     * Authenticated liveness check for Integration Tokens holding the `read` ability.
     */
    public function index(Request $request): JsonResponse
    {
        $token = $request->user('integration');

        return response()->json(['data' => [
            'status' => 'ok',
            'token' => $token instanceof IntegrationToken ? $token->name : null,
        ]]);
    }
}
