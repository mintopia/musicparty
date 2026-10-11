<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    public const SORTABLE = ['id', 'nickname', 'last_login', 'created_at'];

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(
        ?string $search = null,
        int $perPage = 25,
        ?int $id = null,
        ?string $nickname = null,
        string $order = 'nickname',
        string $direction = 'asc',
    ): LengthAwarePaginator {
        $order = in_array($order, self::SORTABLE, true) ? $order : 'nickname';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with('roles')
            ->when($id !== null, fn (Builder $query) => $query->whereKey($id))
            ->tap(fn (Builder $query) => $this->whereNicknameLike($query, $search))
            ->tap(fn (Builder $query) => $this->whereNicknameLike($query, $nickname))
            ->orderBy($order, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<User>  $query
     */
    private function whereNicknameLike(Builder $query, ?string $term): void
    {
        if ($term === null || $term === '') {
            return;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $query->whereRaw("nickname like ? escape '!'", ["%{$escaped}%"]);
    }
}
