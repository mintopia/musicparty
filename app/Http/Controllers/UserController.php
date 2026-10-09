<?php

namespace App\Http\Controllers;

use App\Exceptions\SocialProviderException;
use App\Models\SocialProvider;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class UserController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerate(true);

        return response()->redirectToRoute('home')->with('successMessage', 'You have been logged out');
    }

    public function login_redirect(SocialProvider $socialprovider): RedirectResponse|SymfonyRedirectResponse
    {
        if (! $socialprovider->offersLogin()) {
            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }

        return $socialprovider->redirect();
    }

    public function login_return(SocialProvider $socialprovider): RedirectResponse
    {
        if (Auth::hasUser()) {
            return response()->redirectToIntended(route('home'))->with('successMessage', 'You have been logged in');
        }
        if (! $socialprovider->offersLogin()) {
            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }
        try {
            $user = $socialprovider->user();
            if ($user) {
                if ($user->suspended) {
                    return response()->redirectToRoute('login')->with('errorMessage', 'Your account has been suspended');
                }
                Auth::login($user);
                $user->last_login = Carbon::now();
                $user->save();

                return response()->redirectToIntended(route('home'))->with('successMessage', 'You have been logged in');
            }
        } catch (SocialProviderException $ex) {
            return response()->redirectToRoute('login')->with('errorMessage', $ex->getMessage());
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
    }

    public function login(): Response
    {
        return Inertia::render('Login', [
            'providers' => SocialProvider::query()->orderBy('name')->get()
                ->filter(fn (SocialProvider $provider): bool => $provider->offersLogin())
                ->map(fn (SocialProvider $provider): array => ['code' => $provider->code, 'name' => $provider->name])
                ->values()->all(),
        ]);
    }
}
