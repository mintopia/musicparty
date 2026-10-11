<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Actions\DeleteLinkedAccount as IdentityDeleteLinkedAccount;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteLinkedAccount
{
    public function __construct(private readonly RecordAdminAudit $audit, private readonly IdentityDeleteLinkedAccount $deleteAccount) {}

    public function handle(User $admin, LinkedAccount $account): void
    {
        DB::transaction(function () use ($admin, $account): void {
            $user = $account->user;
            $meta = ['provider' => $account->provider?->code, 'linked_account_id' => $account->id];
            ($this->deleteAccount)($account);
            $this->audit->handle($admin, 'linked_account.deleted', $user, $meta);
        });
    }
}
