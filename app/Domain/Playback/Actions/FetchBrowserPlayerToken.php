<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Players\BrowserPlayer;

readonly class FetchBrowserPlayerToken
{
    public function __construct(private AuthorisesHost $hosts, private HostAccountTokens $tokens) {}

    /**
     * @return array{accessToken: ?string, error: ?string}
     */
    public function __invoke(User $host, Party $party): array
    {
        if ($party->player_kind !== BrowserPlayer::KIND) {
            return ['accessToken' => null, 'error' => 'This Party is not using the Browser Player.'];
        }

        $account = $this->hosts->linkedAccountFor($host, $party->music_provider);

        if ($account === null) {
            return ['accessToken' => null, 'error' => 'Link your music account to use the Browser Player.'];
        }

        try {
            return ['accessToken' => $this->tokens->accessToken($account), 'error' => null];
        } catch (HostAccountNeedsRelink) {
            return ['accessToken' => null, 'error' => 'Your music account needs to be linked again.'];
        } catch (ProviderUnavailableException|ProviderTemporaryFailure) {
            return ['accessToken' => null, 'error' => 'The music provider is unavailable. Try again shortly.'];
        }
    }
}
