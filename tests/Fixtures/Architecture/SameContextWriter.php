<?php

namespace Tests\Fixtures\Architecture;

use App\Domain\Identity\Models\User;

class SameContextWriter
{
    public function write(User $user): void
    {
        $user->forceFill(['name' => 'x'])->save();
        User::query()->where('id', 1)->update(['name' => 'y']);
        $user->increment('logins');
    }
}
