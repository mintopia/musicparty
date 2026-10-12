<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

readonly class UpdateUserProfile
{
    public function __invoke(User $user, string $nickname, bool $agreedToTerms, bool $completedSignup): User
    {
        $user->forceFill([
            'nickname' => $nickname,
            'terms_agreed_at' => $agreedToTerms ? ($user->terms_agreed_at ?? now()) : null,
            'first_login' => ! $completedSignup,
        ])->saveClaimingNickname();

        return $user;
    }
}
