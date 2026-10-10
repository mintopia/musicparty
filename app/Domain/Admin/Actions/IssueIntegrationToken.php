<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\Integration;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;

class IssueIntegrationToken
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    /**
     * @param  list<string>  $abilities
     * @return array{token: Integration, plainText: string}
     */
    public function handle(User $admin, string $name, array $abilities): array
    {
        $abilities = collect($abilities)
            ->map(fn (string $ability): string => IntegrationAbility::from($ability)->value)
            ->unique()
            ->values()
            ->all();

        $integration = Integration::query()->create([
            'name' => $name,
            'issued_by' => $admin->id,
        ]);

        $plainText = $integration->createToken($name, $abilities, $this->expiry())->plainTextToken;

        $this->audit->handle($admin, 'integration_token.issued', null, [
            'token_id' => $integration->id,
            'name' => $name,
            'abilities' => implode(',', $abilities),
        ]);

        return ['token' => $integration->load('tokens'), 'plainText' => $plainText];
    }

    private function expiry(): ?CarbonInterface
    {
        $days = config('musicparty.tokens.integration_ttl_days');

        return $days === null ? null : now()->addDays((int) $days);
    }
}
