<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class LinkedAccountController extends Controller
{
    public function delete(int $user, int $account): Response
    {
        abort(501);
    }

    public function destroy(int $user, int $account): RedirectResponse
    {
        abort(501);
    }
}
