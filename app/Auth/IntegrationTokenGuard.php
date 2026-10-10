<?php

namespace App\Auth;

use App\Domain\Admin\Models\IntegrationToken;
use Illuminate\Http\Request;

class IntegrationTokenGuard
{
    public function __invoke(Request $request): ?IntegrationToken
    {
        $plainText = $request->bearerToken();

        if ($plainText === null || $plainText === '') {
            return null;
        }

        $token = IntegrationToken::query()->where('token_hash', IntegrationToken::hashFor($plainText))->first();

        if ($token === null || $token->isRevoked()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }
}
