<?php

namespace App\Http\Controllers;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Actions\LinkHostAccount;
use App\Domain\Music\Data\HostAccountLinkData;
use App\Domain\Music\Exceptions\AccountAlreadyLinkedException;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use SocialiteProviders\Spotify\Provider;

class SpotifyLinkController extends Controller
{
    private const string SESSION_KEY = 'spotify_link_party_id';

    public function redirect(Request $request, Party $party, AuthorisesHost $authorisesHost): RedirectResponse
    {
        try {
            $authorisesHost($request->user() ?? abort(401), $party);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        $request->session()->put(self::SESSION_KEY, $party->id);

        return $this->driver()->redirect();
    }

    public function callback(Request $request, LinkHostAccount $linkHostAccount): RedirectResponse
    {
        $partyId = $request->session()->pull(self::SESSION_KEY);
        $party = is_int($partyId) ? Party::query()->find($partyId) : null;

        if (! $party instanceof Party) {
            return redirect()->route('home')->with('errorMessage', 'The Spotify link request has expired');
        }

        $remote = $this->driver()->user();

        if (! $remote instanceof OAuthTwoUser) {
            abort(400);
        }

        try {
            $linkHostAccount($request->user() ?? abort(401), $party, new HostAccountLinkData(
                (string) $remote->getId(),
                $remote->getName() ?? $remote->getNickname(),
                (string) $remote->token,
                $remote->refreshToken,
                $remote->expiresIn,
            ));
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (AccountAlreadyLinkedException $exception) {
            return redirect()->route('home')->with('errorMessage', $exception->getMessage());
        }

        return redirect()->route('home')->with('successMessage', 'Spotify account linked');
    }

    private function driver(): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider(Provider::class, [
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
            'redirect' => route('spotify.link.return'),
        ]);

        return $driver->scopes([
            'playlist-read-private',
            'playlist-read-collaborative',
            'playlist-modify-private',
            'playlist-modify-public',
        ]);
    }
}
