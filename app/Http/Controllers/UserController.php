<?php

namespace App\Http\Controllers;

use App\Exceptions\SocialProviderException;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UserSignupRequest;
use App\Models\Party;
use App\Models\Setting;
use App\Models\SocialProvider;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $ids = $request->user()->accounts->pluck('social_provider_id')->toArray();
        $providers = SocialProvider::whereEnabled(true)
            ->get()
            ->filter(function ($provider) use ($ids) {
                return !in_array($provider->id, $ids);
            });

        return view('users.profile', [
            'availableLinks' => $providers,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->regenerate(true);
        return response()->redirectToRoute('home')->with('successMessage', 'You have been logged out');
    }

    public function login_redirect(SocialProvider $socialprovider)
    {
        if (!$socialprovider->enabled || !$socialprovider->auth_enabled) {
            return response()->redirectToRoute('login')->with('errorMessage', 'Unable to login');
        }
        return $socialprovider->redirect();
    }

    public function login_return(SocialProvider $socialprovider)
    {
        if (!$socialprovider->enabled || !$socialprovider->auth_enabled) {
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

    public function login()
    {
        $providers = SocialProvider::whereAuthEnabled(true)->whereEnabled(true)->get();
        return view('users.login', [
            'providers' => $providers,
        ]);
    }

    public function signup(Request $request)
    {
        return view('users.signup', [
            'user' => $request->user(),
            'terms' => Setting::fetch('terms'),
            'privacy' => Setting::fetch('privacypolicy'),
        ]);
    }

    public function signup_process(UserSignupRequest $request)
    {
        $user = $request->user();
        $user->nickname = $request->input('nickname');
        $user->first_login = false;
        $user->terms_agreed_at = Carbon::now();
        $user->save();
        return response()->redirectToRoute('home')->with('successMessage', 'Your account has been created');
    }

    public function edit(Request $request)
    {
        return view('users.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->nickname = $request->input('nickname');
        $user->save();
        return response()->redirectToRoute('user.profile')->with('successMessage', 'Your profile has been updated');
    }

    public function spotify(Request $request)
    {
        if ($request->user()->cannot('create', Party::class)) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', 'You are not able to link Spotify accounts');
        }

        $spotify = null;
        $search = null;
        foreach ($request->user()->accounts as $account) {
            if ($account->provider->code == 'spotify') {
                $spotify = $account;
            } elseif ($account->provider->code == 'spotifysearch') {
                $search = $account;
            }
        }

        return view('users.spotify', [
            'spotify' => $spotify,
            'search' => $search,
        ]);
    }
}
