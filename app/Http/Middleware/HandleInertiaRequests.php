<?php

namespace App\Http\Middleware;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\ActiveTheme;
use App\Services\RealtimeConfig;
use App\Services\Requests\ModerationTabs;
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
                'can' => fn (): array => [
                    'admin' => $request->user()?->can('admin') ?? false,
                    'createParty' => $request->user()?->can('create', Party::class) ?? false,
                ],
            ],
            'flash' => fn (): array => [
                'success' => $request->session()->get('successMessage'),
                'error' => $request->session()->get('errorMessage'),
                'info' => $request->session()->get('infoMessage'),
                'warning' => $request->session()->get('warningMessage'),
            ],
            'site' => fn (): array => [
                'defaultParty' => Setting::fetch('defaultparty'),
                'logoLight' => Setting::fetch('logo-light'),
                'logoDark' => Setting::fetch('logo-dark'),
                'terms' => Setting::fetch('terms'),
                'privacyPolicy' => Setting::fetch('privacypolicy'),
                'coverImage' => Setting::fetch('cover-image'),
                'favicon' => Setting::fetch('favicon'),
            ],
            'reverb' => fn (): array => app(RealtimeConfig::class)->forRequest($request),
            'theme' => fn (): array => app(ActiveTheme::class)->shared() ?? ['tokens' => [], 'stylesheet' => null, 'darkMode' => false],
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
     * @return array<int, array{code: string, name: string, canManage: bool, canModerate: bool, aiReviewEnabled: bool, flaggedCount: int}>
     */
    private function partyProps(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->partyMembers()->with('party')->get()
            ->map(function (PartyMember $member) use ($user): array {
                $canManage = $member->party->canBeManagedBy($user);
                $canModerate = $user->can('moderate', $member->party);

                return [
                    'code' => $member->party->code,
                    'name' => $member->party->name,
                    'canManage' => $canManage,
                    'canModerate' => $canModerate,
                    'aiReviewEnabled' => $canModerate && app(PartyMods::class)->isEnabled($member->party, AiRequestReviewMod::CODE),
                    'flaggedCount' => $canModerate ? app(ModerationTabs::class)->flaggedCount($member->party) : 0,
                ];
            })
            ->values()
            ->all();
    }
}
