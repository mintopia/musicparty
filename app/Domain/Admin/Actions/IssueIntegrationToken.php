<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\IntegrationToken;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

class IssueIntegrationToken
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    /**
     * @param  list<string>  $abilities
     * @return array{token: IntegrationToken, plainText: string}
     */
    public function handle(User $admin, string $name, array $abilities): array
    {
        $abilities = collect($abilities)
            ->map(fn (string $ability): string => IntegrationAbility::from($ability)->value)
            ->unique()
            ->values()
            ->all();

        $plainText = 'mpi_'.Str::random(40);

        $token = IntegrationToken::query()->create([
            'name' => $name,
            'token_hash' => IntegrationToken::hashFor($plainText),
            'abilities' => $abilities,
            'created_by' => $admin->id,
        ]);

        $this->audit->handle($admin, 'integration_token.issued', null, [
            'token_id' => $token->id,
            'name' => $name,
            'abilities' => implode(',', $abilities),
        ]);

        return ['token' => $token, 'plainText' => $plainText];
    }
}
