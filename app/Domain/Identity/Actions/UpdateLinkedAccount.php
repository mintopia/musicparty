<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\LinkedAccount;

readonly class UpdateLinkedAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(LinkedAccount $account, array $attributes): LinkedAccount
    {
        $account->forceFill($attributes)->save();

        return $account;
    }
}
