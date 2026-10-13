<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LinkedAccountController extends Controller
{
    public function destroy(User $user, LinkedAccount $account): RedirectResponse
    {
        if (! $account->canDelete()) {
            return response()->redirectToRoute('admin.users.show', $user->id)->with('errorMessage', 'It is not possible to remove this account');
        }
        $account->delete();

        return response()->redirectToRoute('admin.users.show', $account->user->id)->with('successMessage', 'The account has been deleted');
    }

    public function delete(User $user, LinkedAccount $account): RedirectResponse|Response
    {
        if (! $account->canDelete()) {
            return response()->redirectToRoute('admin.users.show', $user->id)->with('errorMessage', 'It is not possible to remove this account');
        }

        return Inertia::render('Admin/LinkedAccounts/Delete', [
            'user' => ['id' => $user->id, 'nickname' => $user->nickname],
            'account' => ['id' => $account->id, 'provider' => $account->provider?->name],
        ]);
    }
}
