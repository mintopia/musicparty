<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesSpotifyLinking;
use App\Domain\Music\Actions\LinkHostAccount;
use App\Domain\Music\Actions\SpotifyControlLinkStatus;
use App\Domain\Music\Actions\SpotifySearchLinkStatus;
use App\Domain\Music\Actions\UnlinkHostAccount;
use App\Domain\Music\Data\HostAccountLinkData;
use App\Domain\Music\Exceptions\AccountAlreadyLinkedException;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Music\Providers\Spotify\SpotifyScopes;
use App\Domain\Party\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use SocialiteProviders\Spotify\Provider;

class SpotifyLinkController extends Controller
{
    private const string RETURN_KEY = 'spotify_link_return';

    private const string ACCOUNT_KEY = 'spotify_link_account';

    public function index(Request $request, AuthorisesSpotifyLinking $authorises, SpotifyControlLinkStatus $status, SpotifySearchLinkStatus $searchStatus): Response
    {
        $user = $this->currentUser($request);
        $this->authoriseLinking($authorises, $user);

        return Inertia::render('Spotify/Link', [
            'control' => $status($user),
            'search' => $searchStatus->enabled() ? $searchStatus($user) : null,
            'search_enabled' => $searchStatus->enabled(),
            'can_create_party' => $user->can('create', Party::class),
            'link_url' => route('spotify.link', ['account' => 'control', 'return' => $this->safeReturnPath($request->query('return'))]),
            'unlink_url' => route('spotify.unlink', ['account' => 'control']),
            'search_link_url' => route('spotify.link', ['account' => 'search', 'return' => $this->safeReturnPath($request->query('return'))]),
            'search_unlink_url' => route('spotify.unlink', ['account' => 'search']),
            'create_party_url' => route('parties.create'),
        ]);
    }

    public function redirect(Request $request, string $account, AuthorisesSpotifyLinking $authorises, SpotifySearchLinkStatus $searchStatus): RedirectResponse
    {
        $this->authoriseLinking($authorises, $this->currentUser($request));
        abort_unless($account === 'control' || ($account === 'search' && $searchStatus->enabled()), 404);

        $request->session()->put(self::RETURN_KEY, $request->query('return'));
        $request->session()->put(self::ACCOUNT_KEY, $account);

        return $this->driver($account)->redirect();
    }

    public function callback(Request $request, LinkHostAccount $linkHostAccount): RedirectResponse
    {
        $returnTo = $this->safeReturnPath($request->session()->pull(self::RETURN_KEY));
        $account = $request->session()->pull(self::ACCOUNT_KEY) === 'search' ? 'search' : 'control';
        $remote = $this->driver($account)->user();

        if (! $remote instanceof OAuthTwoUser) {
            abort(400);
        }

        $granted = $remote->approvedScopes;

        try {
            $linkHostAccount($this->currentUser($request), new HostAccountLinkData(
                (string) $remote->getId(),
                $remote->getName() ?? $remote->getNickname(),
                (string) $remote->token,
                $remote->refreshToken,
                $remote->expiresIn,
                $account === 'search' ? implode(' ', $granted) : implode(' ', $granted === [] ? SpotifyScopes::control() : $granted),
            ), $account === 'search' ? SpotifyApi::SEARCH_ID : SpotifyApi::ID);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (AccountAlreadyLinkedException $exception) {
            return redirect($returnTo)->with('error', $exception->getMessage());
        }

        return redirect($returnTo)->with('success', $account === 'search' ? 'Spotify Search account linked' : 'Spotify account linked');
    }

    public function destroy(Request $request, string $account, AuthorisesSpotifyLinking $authorises, UnlinkHostAccount $unlink): RedirectResponse
    {
        $user = $this->currentUser($request);
        $this->authoriseLinking($authorises, $user);

        $unlink($user, $account === 'search' ? SpotifyApi::SEARCH_ID : SpotifyApi::ID);

        return redirect()->route('spotify.index')->with('success', $account === 'search' ? 'Spotify Search account unlinked' : 'Spotify account unlinked');
    }

    private function authoriseLinking(AuthorisesSpotifyLinking $authorises, User $user): void
    {
        try {
            $authorises($user);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }
    }

    private function safeReturnPath(mixed $path): string
    {
        $fallback = route('spotify.index', absolute: false);

        if (! is_string($path) || ! preg_match('#^/(?![/\\\\])#', $path) || preg_match('/[\x00-\x1f\x7f]/', $path)) {
            return $fallback;
        }

        return $path;
    }

    private function driver(string $account): AbstractProvider
    {
        $application = $account === 'search' ? SpotifyApi::SEARCH_ID : SpotifyApi::ID;

        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider(Provider::class, [
            'client_id' => config("services.{$application}.client_id"),
            'client_secret' => config("services.{$application}.client_secret"),
            'redirect' => route('spotify.link.return'),
        ]);

        return $account === 'search' ? $driver->scopes([]) : $driver->scopes(SpotifyScopes::control());
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
