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
            'reverb' => fn (): array => app(RealtimeConfig::class)->forApp(),
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

        $isAdmin = $user->hasRole('admin');
        $members = $user->partyMembers()->with(['party', 'role'])->get()
            ->map(fn (PartyMember $member): array => [
                'member' => $member,
                'canManage' => $member->party->roleCodeForMember($member) === 'owner',
                'canModerate' => $isAdmin || in_array($member->party->roleCodeForMember($member), ['owner', 'moderator'], true),
            ]);

        $moderatedIds = $members->where('canModerate', true)->map(fn (array $row): int => $row['member']->party_id)->all();
        $flaggedCounts = $moderatedIds === [] ? [] : app(ModerationTabs::class)->flaggedCounts($moderatedIds);
        $aiReviewIds = $moderatedIds === [] ? [] : app(PartyMods::class)->partyIdsWithEnabled($moderatedIds, AiRequestReviewMod::CODE);

        return $members
            ->map(fn (array $row): array => [
                'code' => $row['member']->party->code,
                'name' => $row['member']->party->name,
                'canManage' => $row['canManage'],
                'canModerate' => $row['canModerate'],
                'aiReviewEnabled' => in_array($row['member']->party_id, $aiReviewIds, true),
                'flaggedCount' => $flaggedCounts[$row['member']->party_id] ?? 0,
            ])
            ->values()
            ->all();
    }
}
