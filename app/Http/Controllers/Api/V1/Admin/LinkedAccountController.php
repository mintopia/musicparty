<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\DeleteLinkedAccount;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LinkedAccountController extends Controller
{
    public function destroy(Request $request, User $user, LinkedAccount $account, DeleteLinkedAccount $deleteAccount): Response
    {
        abort_unless($account->canDelete(), 422, 'This linked account cannot be deleted.');

        $deleteAccount->handle($request->user() ?? throw new AuthenticationException, $account);

        return response()->noContent();
    }
}
