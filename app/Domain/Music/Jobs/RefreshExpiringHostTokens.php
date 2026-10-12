<?php

namespace App\Domain\Music\Jobs;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;
use App\Domain\Music\Providers\Spotify\SpotifyApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RefreshExpiringHostTokens implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    private const int WINDOW_MINUTES = 5;

    private const int CHUNK_SIZE = 50;

    public function handle(HostAccountTokens $tokens): void
    {
        LinkedAccount::query()
            ->with('provider')
            ->where('needs_relink', false)
            ->whereNotNull('refresh_token')
            ->whereNotNull('access_token_expires_at')
            ->where('access_token_expires_at', '<=', now()->addMinutes(self::WINDOW_MINUTES))
            ->whereHas('provider', fn (Builder $query) => $query->whereIn('code', [SpotifyApi::ID, SpotifyApi::SEARCH_ID]))
            ->chunkById(self::CHUNK_SIZE, function ($accounts) use ($tokens): void {
                foreach ($accounts as $account) {
                    try {
                        $tokens->accessToken($account);
                    } catch (HostAccountNeedsRelink) {
                        continue;
                    } catch (ProviderUnavailableException|ProviderTemporaryFailure $exception) {
                        report($exception);
                    }
                }
            });
    }
}
