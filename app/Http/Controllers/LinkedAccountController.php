<?php

namespace App\Http\Controllers;

use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkedAccountController extends Controller
{
    public function create(Request $request, SocialProvider $socialprovider): RedirectResponse
    {
        if ($request->header('referer') == route('user.spotify')) {
            $request->session()->flash('returnToSpotify', true);
        }

        return $socialprovider->redirect();
    }

    public function store(Request $request, SocialProvider $socialprovider): RedirectResponse
    {
        $url = 'user.profile';
        if ($request->session()->get('returnToSpotify')) {
            $url = 'user.spotify';
        }
        try {
            $socialprovider->user();

            return response()->redirectToRoute($url)->with('successMessage', "Your {$socialprovider->name} account has been linked");
        } catch (SocialProviderException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw $ex;
            // Do Nothing
        }
    }

    public function delete(LinkedAccount $linkedaccount): RedirectResponse|View
    {
        if (! $linkedaccount->canDelete()) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', 'It is not possible to remove this account');
        }

        return view('users.linkedaccounts.delete', [
            'account' => $linkedaccount,
        ]);
    }

    public function destroy(LinkedAccount $linkedaccount): RedirectResponse
    {
        if (! $linkedaccount->canDelete()) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', 'It is not possible to remove this account');
        }
        $linkedaccount->delete();

        return response()->redirectToRoute('user.profile')->with('successMessage', 'The account has been removed');
    }
}
