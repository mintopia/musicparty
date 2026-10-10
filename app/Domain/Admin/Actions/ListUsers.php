<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        $term = $search === null ? null : str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        return User::query()
            ->with('roles')
            ->when($term !== null && $term !== '', fn ($query) => $query->whereRaw("nickname like ? escape '!'", ["%{$term}%"]))
            ->orderBy('nickname')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
