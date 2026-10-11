<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\LinkedAccount;
use Illuminate\Validation\ValidationException;

readonly class DeleteLinkedAccount
{
    public function __invoke(LinkedAccount $account): void
    {
        if (! $account->canDelete()) {
            throw ValidationException::withMessages(['admin' => 'This linked account cannot be deleted.']);
        }

        $account->delete();
    }
}
