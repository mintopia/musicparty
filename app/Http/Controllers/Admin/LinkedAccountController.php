<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\DeleteLinkedAccount;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkedAccountController extends Controller
{
    public function delete(User $user, LinkedAccount $account): Response|RedirectResponse
    {
        if (! $account->canDelete()) {
            return redirect()->route('admin.users.show', $user)->with('error', 'This linked account cannot be deleted.');
        }

        $account->load('provider');

        return Inertia::render('Admin/LinkedAccounts/Delete', [
            'user' => ['id' => $user->id, 'nickname' => $user->nickname],
            'account' => [
                'id' => $account->id,
                'provider' => $account->provider?->name,
                'name' => $account->name,
                'email' => $account->email,
                'external_id' => $account->external_id,
            ],
        ]);
    }

    public function destroy(Request $request, User $user, LinkedAccount $account, DeleteLinkedAccount $deleteAccount): RedirectResponse
    {
        $deleteAccount->handle($request->user() ?? throw new AuthenticationException, $account);

        return redirect()->route('admin.users.show', $user)->with('success', 'Linked account deleted.');
    }
}
