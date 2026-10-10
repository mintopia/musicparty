<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Integration;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeIntegrationToken
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, Integration $integration): Integration
    {
        if ($integration->isRevoked()) {
            return $integration;
        }

        $abilities = $integration->abilities();

        DB::transaction(function () use ($integration): void {
            $integration->forceFill(['revoked_at' => now()])->save();
            $integration->tokens()->delete();
        });

        $this->audit->handle($admin, 'integration_token.revoked', null, [
            'token_id' => $integration->id,
            'name' => $integration->name,
            'abilities' => implode(',', $abilities),
        ]);

        return $integration;
    }
}
