<?php

namespace App\Http\Middleware;

use App\Models\PartyMember;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn (): array => [
                'user' => $this->userProps($request->user()),
                'parties' => fn (): array => $this->partyProps($request->user()),
            ],
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'status' => $request->session()->get('status'),
            ],
            'reverb' => [
                'appKey' => config('reverb.apps.apps.0.key'),
            ],
            'instanceName' => fn (): ?string => Setting::fetch('name'),
        ];
    }

    /**
     * @return array{id: int, nickname: string, avatar: string, roles: array<int, string>, impersonating: bool}|null
     */
    private function userProps(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatarUrl(),
            'roles' => $user->roles->map(fn (Role $role): string => $role->code)->values()->all(),
            'impersonating' => (bool) request()->session()->get('impersonating', false),
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, canManage: bool}>
     */
    private function partyProps(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->partyMembers()->with('party')->get()
            ->map(fn (PartyMember $member): array => [
                'code' => $member->party->code,
                'name' => $member->party->name,
                'canManage' => $member->party->canBeManagedBy($user),
            ])
            ->values()
            ->all();
    }
}
