<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\IssueIntegrationToken;
use App\Domain\Admin\Actions\ListIntegrationTokens;
use App\Domain\Admin\Actions\RevokeIntegrationToken;
use App\Domain\Admin\Models\Integration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IssueIntegrationTokenRequest;
use App\Http\Resources\V1\IntegrationTokenResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationTokenController extends Controller
{
    public function index(ListIntegrationTokens $listTokens): AnonymousResourceCollection
    {
        return IntegrationTokenResource::collection($listTokens->handle());
    }

    public function store(IssueIntegrationTokenRequest $request, IssueIntegrationToken $issueToken): JsonResponse
    {
        /** @var list<string> $abilities */
        $abilities = $request->input('abilities');

        $issued = $issueToken->handle(
            $request->user() ?? throw new AuthenticationException,
            $request->string('name')->toString(),
            $abilities,
        );

        return new IntegrationTokenResource($issued['token'])
            ->additional(['meta' => ['token' => $issued['plainText']]])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Integration $token, RevokeIntegrationToken $revokeToken): IntegrationTokenResource
    {
        return new IntegrationTokenResource($revokeToken->handle($request->user() ?? throw new AuthenticationException, $token));
    }
}
