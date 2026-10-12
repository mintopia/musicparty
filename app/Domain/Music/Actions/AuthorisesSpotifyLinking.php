<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\Gate;

class AuthorisesSpotifyLinking
{
    public function allows(User $user): bool
    {
        return Gate::forUser($user)->allows('create', Party::class)
            || Party::query()->where('user_id', $user->id)->exists();
    }

    /**
     * @throws NotHostException
     */
    public function __invoke(User $user): void
    {
        if (! $this->allows($user)) {
            throw new NotHostException('Only a Host or a user who can create Parties can link a Spotify account.');
        }
    }
}
