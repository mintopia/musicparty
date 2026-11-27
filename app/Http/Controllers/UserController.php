<?php

namespace App\Http\Controllers;

use App\Exceptions\SocialProviderException;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UserSignupRequest;
use App\Models\Party;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Services\PartyInvite;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function profile(Request $request): Response
    {
        $user = $this->resolveAuthenticatedUser($request);
        $user->load('accounts.provider');
        $ids = $user->accounts->pluck('social_provider_id')->toArray();
        $providers = SocialProvider::whereEnabled(true)
            ->get()
            ->filter(fn ($provider) => ! in_array($provider->id, $ids));

        return Inertia::render('Users/Profile', [
            'user' => [
                'nickname' => $user->nickname,
                'email' => $user->getEmail(),
                'avatar' => $user->avatarUrl(),
            ],
            'accounts' => $user->accounts->map(fn ($account) => [
                'id' => $account->id,
                'providerCode' => $account->provider?->code,
                'providerName' => $account->provider?->name,
                'name' => $account->name,
                'avatarUrl' => $account->avatar_url,
                'canDelete' => $account->canDelete(),
            ])->values(),
            'availableLinks' => $providers->map(fn ($provider) => [
                'code' => $provider->code,
                'name' => $provider->name,
            ])->values(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerate(true);

        return response()->redirectToRoute('home')->with('successMessage', 'You have been logged out');
    }

    public function login_redirect(SocialProvider $socialprovider): RedirectResponse
    {
        if (! $socialprovider->enabled || ! $socialprovider->auth_enabled) {
            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }

        return $socialprovider->redirect();
    }

    public function login_return(Request $request, SocialProvider $socialprovider): RedirectResponse
    {
        if (Auth::hasUser()) {
            return response()->redirectToIntended(route('home'))->with('successMessage', 'You have been logged in');
        }
        if (! $socialprovider->enabled || ! $socialprovider->auth_enabled) {
            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }
        try {
            $user = $socialprovider->user();
            if ($user) {
                if ($user->suspended) {
                    return response()->redirectToRoute('login')->with('errorMessage', 'Your account has been suspended');
                }
                $intended = $request->session()->get('url.intended');
                Auth::login($user);
                $user->last_login = Carbon::now();
                $user->save();
                $response = response()->redirectToIntended(route('home'))->with('successMessage', 'You have been logged in');
                if ($user->first_login && $intended !== null) {
                    $request->session()->put('url.intended', $intended);
                }

                return $response;
            }
        } catch (SocialProviderException $ex) {
            return response()->redirectToRoute('login')->with('errorMessage', $ex->getMessage());
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
    }

    public function login(Request $request, PartyInvite $invite): Response
    {
        $providers = SocialProvider::whereAuthEnabled(true)->whereEnabled(true)->get();

        return Inertia::render('Users/Login', [
            'party' => $invite->fromIntended($request),
            'providers' => $providers->map(fn ($provider) => [
                'code' => $provider->code,
                'name' => $provider->name,
            ])->values(),
        ]);
    }

    public function signup(Request $request, PartyInvite $invite): Response
    {
        $user = $this->resolveAuthenticatedUser($request);

        return Inertia::render('Users/Signup', [
            'party' => $invite->fromIntended($request),
            'user' => [
                'nickname' => $user->nickname,
                'avatar' => $user->avatarUrl(),
            ],
            'terms' => Setting::fetch('terms'),
            'privacy' => Setting::fetch('privacypolicy'),
        ]);
    }

    public function signup_process(UserSignupRequest $request): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $user->nickname = $request->input('nickname');
        $user->first_login = false;
        $user->terms_agreed_at = now();
        $user->save();

        return response()->redirectToIntended(route('home'))->with('successMessage', 'Your account has been created');
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => ['nickname' => $this->resolveAuthenticatedUser($request)->nickname],
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $user->nickname = $request->input('nickname');
        $user->save();

        return response()->redirectToRoute('user.profile')->with('successMessage', 'Your profile has been updated');
    }

    public function spotify(Request $request): RedirectResponse|Response
    {
        if ($this->resolveAuthenticatedUser($request)->cannot('create', Party::class)) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', 'You are not able to link Spotify accounts');
        }

        $spotify = null;
        $search = null;
        $user = $this->resolveAuthenticatedUser($request);
        $user->load('accounts.provider');
        foreach ($user->accounts as $account) {
            if ($account->provider?->code == 'spotify') {
                $spotify = $account;
            } elseif ($account->provider?->code == 'spotifysearch') {
                $search = $account;
            }
        }

        return Inertia::render('Users/Spotify', [
            'spotify' => $spotify ? ['name' => $spotify->name] : null,
            'search' => $search ? ['name' => $search->name] : null,
        ]);
    }
}
