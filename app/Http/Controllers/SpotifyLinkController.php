<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesSpotifyLinking;
use App\Domain\Music\Actions\LinkHostAccount;
use App\Domain\Music\Actions\SpotifyControlLinkStatus;
use App\Domain\Music\Actions\UnlinkHostAccount;
use App\Domain\Music\Data\HostAccountLinkData;
use App\Domain\Music\Exceptions\AccountAlreadyLinkedException;
use App\Domain\Music\Exceptions\NotHostException;
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

    public function index(Request $request, AuthorisesSpotifyLinking $authorises, SpotifyControlLinkStatus $status): Response
    {
        $user = $this->currentUser($request);
        $this->authoriseLinking($authorises, $user);

        return Inertia::render('Spotify/Link', [
            'control' => $status($user),
            'can_create_party' => $user->can('create', Party::class),
            'link_url' => route('spotify.link', ['account' => 'control', 'return' => $this->safeReturnPath($request->query('return'))]),
            'unlink_url' => route('spotify.unlink', ['account' => 'control']),
            'create_party_url' => route('parties.create'),
        ]);
    }

    public function redirect(Request $request, string $account, AuthorisesSpotifyLinking $authorises): RedirectResponse
    {
        $this->authoriseLinking($authorises, $this->currentUser($request));
        abort_unless($account === 'control', 404);

        $request->session()->put(self::RETURN_KEY, $request->query('return'));

        return $this->driver()->redirect();
    }

    public function callback(Request $request, LinkHostAccount $linkHostAccount): RedirectResponse
    {
        $returnTo = $this->safeReturnPath($request->session()->pull(self::RETURN_KEY));
        $remote = $this->driver()->user();

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
                implode(' ', $granted === [] ? SpotifyScopes::control() : $granted),
            ));
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (AccountAlreadyLinkedException $exception) {
            return redirect($returnTo)->with('error', $exception->getMessage());
        }

        return redirect($returnTo)->with('success', 'Spotify account linked');
    }

    public function destroy(Request $request, string $account, AuthorisesSpotifyLinking $authorises, UnlinkHostAccount $unlink): RedirectResponse
    {
        $user = $this->currentUser($request);
        $this->authoriseLinking($authorises, $user);
        abort_unless($account === 'control', 404);

        $unlink($user);

        return redirect()->route('spotify.index')->with('success', 'Spotify account unlinked');
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

    private function driver(): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider(Provider::class, [
            'client_id' => config('services.spotify.client_id'),
            'client_secret' => config('services.spotify.client_secret'),
            'redirect' => route('spotify.link.return'),
        ]);

        return $driver->scopes(SpotifyScopes::control());
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
