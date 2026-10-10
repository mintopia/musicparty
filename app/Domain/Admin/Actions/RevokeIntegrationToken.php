<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\IntegrationToken;
use App\Domain\Identity\Models\User;

class RevokeIntegrationToken
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, IntegrationToken $token): IntegrationToken
    {
        if ($token->isRevoked()) {
            return $token;
        }

        $token->forceFill(['revoked_at' => now()])->save();

        $this->audit->handle($admin, 'integration_token.revoked', null, [
            'token_id' => $token->id,
            'name' => $token->name,
            'abilities' => implode(',', $token->abilities),
        ]);

        return $token;
    }
}
