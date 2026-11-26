<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModUserSearchController extends Controller
{
    public function party(Request $request, Party $party): JsonResponse
    {
        $this->authorize('update', $party);

        /** @var Builder<User> $query */
        $query = User::query();
        $query->whereIn('id', $party->members()->select('user_id'));

        return $this->respond($query, $request);
    }

    public function instance(Request $request): JsonResponse
    {
        /** @var Builder<User> $query */
        $query = User::query();

        return $this->respond($query, $request);
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function respond(Builder $query, Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $query->where('nickname', 'LIKE', '%'.addcslashes($term, '%_\\').'%');
        }

        return response()->json([
            'data' => $query->orderBy('nickname')->limit(10)->get(['id', 'nickname'])
                ->map(fn (User $user): array => ['id' => $user->id, 'nickname' => $user->nickname])
                ->all(),
        ]);
    }
}
