<?php

namespace App\Http\Controllers;

use App\Exceptions\SocialCallbackRejectedException;
use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        } catch (SocialCallbackRejectedException $ex) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', $ex->getMessage());
        } catch (SocialProviderException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw $ex;
            // Do Nothing
        }
    }

    public function delete(LinkedAccount $linkedaccount): RedirectResponse|Response
    {
        if (! $linkedaccount->canDelete()) {
            return response()->redirectToRoute('user.profile')->with('errorMessage', 'It is not possible to remove this account');
        }

        $linkedaccount->loadMissing('provider');

        return Inertia::render('Users/LinkedAccountDelete', [
            'account' => [
                'id' => $linkedaccount->id,
                'providerName' => $linkedaccount->provider?->name,
            ],
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
