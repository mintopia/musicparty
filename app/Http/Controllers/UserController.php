<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\CompleteSocialLogin;
use App\Domain\Identity\Actions\ListLoginProviders;
use App\Domain\Identity\Actions\StartSocialLogin;
use App\Domain\Identity\Exceptions\LoginRefusedException;
use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class UserController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->redirectToRoute('home')->with('successMessage', 'You have been logged out');
    }

    public function login(ListLoginProviders $listLoginProviders): Response
    {
        return Inertia::render('Login', [
            'providers' => $listLoginProviders()->map(fn (SocialProvider $provider): array => [
                'code' => $provider->code,
                'name' => $provider->name,
                'url' => route('login.redirect', $provider->code),
            ])->all(),
            'error' => session('errorMessage'),
        ]);
    }

    public function login_redirect(SocialProvider $socialprovider, StartSocialLogin $startSocialLogin): RedirectResponse|SymfonyRedirectResponse
    {
        try {
            return $startSocialLogin($socialprovider);
        } catch (LoginRefusedException $ex) {
            return response()->redirectToRoute('login')->with('errorMessage', $ex->getMessage());
        } catch (Throwable $ex) {
            Log::error($ex->getMessage());

            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }
    }

    public function login_return(SocialProvider $socialprovider, CompleteSocialLogin $completeSocialLogin): RedirectResponse
    {
        try {
            $user = $completeSocialLogin($socialprovider);
        } catch (LoginRefusedException $ex) {
            return response()->redirectToRoute('login')->with('errorMessage', $ex->getMessage());
        } catch (Throwable $ex) {
            Log::error($ex->getMessage());

            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }

        Auth::login($user);
        request()->session()->regenerate();

        if ($user->first_login) {
            return response()->redirectToRoute('login.signup');
        }

        return response()->redirectToIntended(route('home'))->with('successMessage', 'You have been logged in');
    }
}
