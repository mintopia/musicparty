<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\User;

class LinkedAccountController extends Controller
{
    public function destroy(User $user, LinkedAccount $account)
    {
        if (!$account->canDelete()) {
            return response()->redirectToRoute('admin.users.show')->with('errorMessage', 'It is not possible to remove this account');
        }
        $account->delete();
        return response()->redirectToRoute('admin.users.show', $account->user->id)->with('successMessage', 'The account has been deleted');
    }

    public function delete(User $user, LinkedAccount $account)
    {
        if (!$account->canDelete()) {
            return response()->redirectToRoute('admin.users.show')->with('errorMessage', 'It is not possible to remove this account');
        }
        return view('admin.linkedaccounts.delete', [
            'account' => $account,
        ]);
    }
}
