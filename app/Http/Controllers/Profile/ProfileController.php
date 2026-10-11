<?php

namespace App\Http\Controllers\Profile;

use App\Domain\Identity\Actions\UpdateNickname;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $this->currentUser($request)->load('accounts.provider');
        $linkedProviderIds = $user->accounts->pluck('social_provider_id')->all();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'nickname' => $user->nickname,
                'email' => $user->getEmail(),
                'avatar_url' => $user->avatarUrl(),
            ],
            'accounts' => $user->accounts->map(fn (LinkedAccount $account): array => [
                'id' => $account->id,
                'provider' => $account->provider?->name,
                'provider_code' => $account->provider?->code,
                'name' => $account->name,
                'avatar_url' => $account->avatar_url,
                'can_delete' => $account->canDelete(),
            ])->values()->all(),
            'available_links' => SocialProvider::query()
                ->where('enabled', true)
                ->whereNotIn('id', $linkedProviderIds)
                ->orderBy('name')
                ->get()
                ->filter(fn (SocialProvider $provider): bool => $provider->isAvailableForLinking())
                ->map(fn (SocialProvider $provider): array => [
                    'code' => $provider->code,
                    'name' => $provider->name,
                    'url' => route('profile.accounts.link', $provider->code),
                ])->values()->all(),
        ]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'nickname' => $this->currentUser($request)->nickname,
        ]);
    }

    public function update(UpdateProfileRequest $request, UpdateNickname $updateNickname): RedirectResponse
    {
        $updateNickname($this->currentUser($request), $request->string('nickname')->trim()->toString());

        return redirect()->route('profile.show')->with('success', 'Your profile has been updated');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
