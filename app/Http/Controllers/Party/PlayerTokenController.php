<?php

namespace App\Http\Controllers\Party;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\IssuePlayerToken;
use App\Domain\Playback\Actions\ListPlayerTokens;
use App\Domain\Playback\Actions\RevokePlayerToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Party\IssuePlayerTokenRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class PlayerTokenController extends Controller
{
    public function index(Request $request, Party $party, ListPlayerTokens $listPlayerTokens): Response
    {
        try {
            $tokens = $listPlayerTokens($this->actor($request), $party);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return Inertia::render('Party/PlayerTokens', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'tokens' => $tokens->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->values()->all(),
            'issuedToken' => $request->session()->get('issued_player_token'),
        ]);
    }

    public function store(IssuePlayerTokenRequest $request, Party $party, IssuePlayerToken $issuePlayerToken): RedirectResponse
    {
        try {
            $token = $issuePlayerToken($this->actor($request), $party, $request->validated('name'));
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return to_route('parties.player-tokens.index', ['party' => $party->code])
            ->with('issued_player_token', ['name' => $token->accessToken->name, 'token' => $token->plainTextToken]);
    }

    public function destroy(Request $request, Party $party, int $token, RevokePlayerToken $revokePlayerToken): RedirectResponse
    {
        try {
            $revokePlayerToken($this->actor($request), $party, $token);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return to_route('parties.player-tokens.index', ['party' => $party->code]);
    }

    private function actor(Request $request): User
    {
        return $request->user() ?? throw new AuthenticationException;
    }
}
