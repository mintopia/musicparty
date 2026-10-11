<?php

namespace App\Http\Controllers\Profile;

use App\Domain\Identity\Actions\CompleteAccountLink;
use App\Domain\Identity\Actions\DeleteLinkedAccount;
use App\Domain\Identity\Actions\StartAccountLink;
use App\Domain\Identity\Exceptions\AccountLinkRefusedException;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class LinkedAccountController extends Controller
{
    public function create(SocialProvider $socialprovider, StartAccountLink $startAccountLink): RedirectResponse|SymfonyRedirectResponse
    {
        try {
            return $startAccountLink($socialprovider);
        } catch (AccountLinkRefusedException $ex) {
            return redirect()->route('profile.show')->with('error', $ex->getMessage());
        } catch (Throwable $ex) {
            Log::error($ex->getMessage());

            return redirect()->route('profile.show')->with('error', 'Unable to link your account');
        }
    }

    public function store(Request $request, SocialProvider $socialprovider, CompleteAccountLink $completeAccountLink): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        try {
            $completeAccountLink($user, $socialprovider);
        } catch (AccountLinkRefusedException $ex) {
            return redirect()->route('profile.show')->with('error', $ex->getMessage());
        } catch (Throwable $ex) {
            Log::error($ex->getMessage());

            return redirect()->route('profile.show')->with('error', 'Unable to link your account');
        }

        return redirect()->route('profile.show')->with('success', "Your {$socialprovider->name} account has been linked");
    }

    public function delete(LinkedAccount $linkedaccount): Response|RedirectResponse
    {
        if (! $linkedaccount->canDelete()) {
            return redirect()->route('profile.show')->with('error', 'You cannot remove your last login account.');
        }

        $linkedaccount->load('provider');

        return Inertia::render('Profile/DeleteAccount', [
            'account' => [
                'id' => $linkedaccount->id,
                'provider' => $linkedaccount->provider?->name,
                'name' => $linkedaccount->name,
                'email' => $linkedaccount->email,
            ],
        ]);
    }

    public function destroy(LinkedAccount $linkedaccount, DeleteLinkedAccount $deleteLinkedAccount): RedirectResponse
    {
        if (! $linkedaccount->canDelete()) {
            return redirect()->route('profile.show')->with('error', 'You cannot remove your last login account.');
        }

        $deleteLinkedAccount($linkedaccount);

        return redirect()->route('profile.show')->with('success', 'Linked account removed');
    }
}
