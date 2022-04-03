<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    protected function login(Request $request)
    {
        return Socialite::driver('discord')->redirect();
    }

    protected function login_redirect()
    {
        $discordUser = Socialite::driver('discord')->user();
        $localUser = User::fromDiscord($discordUser);
        Auth::login($localUser);
        return response()->redirectToIntended('/');
    }

    protected function logout()
    {
        Auth::logout();
        return response()->redirectToRoute('home');
    }

    protected function spotify_link(Request $request)
    {
        return Socialite::driver('spotify')->scopes(config('services.spotify.scopes'))->redirect();
    }

    protected function spotify_redirect(Request $request)
    {
        $spotifyUser = Socialite::driver('spotify')->user();
        $request->user()->updateFromSpotify($spotifyUser);
        return response()->redirectToRoute('home')->with('successMessage', 'Your Spotify account has been linked');
    }
}
