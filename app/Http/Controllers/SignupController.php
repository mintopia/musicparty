<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\CompleteSignup;
use App\Domain\Identity\Models\User;
use App\Http\Requests\SignupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignupController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user->first_login) {
            return redirect()->route('home');
        }

        return Inertia::render('Signup', [
            'nickname' => $user->nickname,
            'termsUrl' => config('app.terms_url'),
            'privacyUrl' => config('app.privacy_url'),
        ]);
    }

    public function store(SignupRequest $request, CompleteSignup $completeSignup): RedirectResponse
    {
        $completeSignup($this->currentUser($request), $request->string('nickname')->trim()->toString());

        return redirect()->intended(route('home'))->with('successMessage', 'Welcome to Music Party');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
