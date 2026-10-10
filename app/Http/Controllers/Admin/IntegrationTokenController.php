<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\IssueIntegrationToken;
use App\Domain\Admin\Actions\ListIntegrationTokens;
use App\Domain\Admin\Actions\RevokeIntegrationToken;
use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\Integration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IssueIntegrationTokenRequest;
use App\Http\Resources\V1\IntegrationTokenResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationTokenController extends Controller
{
    public function index(Request $request, ListIntegrationTokens $listTokens): Response
    {
        return Inertia::render('Admin/Tokens/Index', [
            'tokens' => IntegrationTokenResource::collection($listTokens->handle()),
            'abilities' => array_column(IntegrationAbility::cases(), 'value'),
            'issued' => $request->session()->get('issued_token'),
        ]);
    }

    public function store(IssueIntegrationTokenRequest $request, IssueIntegrationToken $issueToken): RedirectResponse
    {
        /** @var list<string> $abilities */
        $abilities = $request->input('abilities');

        $issued = $issueToken->handle(
            $request->user() ?? throw new AuthenticationException,
            $request->string('name')->toString(),
            $abilities,
        );

        return back()->with('issued_token', [
            'name' => $issued['token']->name,
            'value' => $issued['plainText'],
        ]);
    }

    public function destroy(Request $request, Integration $token, RevokeIntegrationToken $revokeToken): RedirectResponse
    {
        $revokeToken->handle($request->user() ?? throw new AuthenticationException, $token);

        return back();
    }
}
