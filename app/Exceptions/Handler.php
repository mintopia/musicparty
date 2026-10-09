<?php

namespace App\Exceptions;

use App\Domain\Party\Exceptions\FallbackPlaylistInsufficient;
use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SpotifyWebAPI\SpotifyWebAPIException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (SpotifyWebAPIException $e) {
            if ($e->isRateLimited()) {
                Log::critical('Spotify Rate Limit Hit');
            }
        });

        $this->map(fn (IncompatibleProviderException $e): ValidationException => ValidationException::withMessages([
            'player_kind' => [$e->getMessage()],
        ]));

        $this->map(fn (FallbackPlaylistInsufficient $e): ValidationException => ValidationException::withMessages([
            'fallback_playlist_id' => [$e->getMessage()],
        ]));

        $this->map(fn (InvalidPartyTransition $e): ValidationException => ValidationException::withMessages([
            'state' => [$e->getMessage()],
        ]));

        $this->map(fn (MembershipActionRefused $e): Throwable => $e->status() === MembershipActionRefused::FORBIDDEN
            ? new AccessDeniedHttpException($e->getMessage(), $e)
            : ValidationException::withMessages(['member' => [$e->getMessage()]]));
    }
}
