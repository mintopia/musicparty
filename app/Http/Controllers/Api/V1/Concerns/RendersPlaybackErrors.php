<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\DeviceNotFoundException;
use App\Exceptions\DeviceRestrictedException;
use App\Exceptions\NoActiveDeviceException;
use App\Exceptions\NothingToPlayException;
use App\Exceptions\NotPlayableException;
use App\Exceptions\PlaybackBusyException;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderPremiumRequiredException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Models\Party;
use App\Services\Playback\StartPlayback;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait RendersPlaybackErrors
{
    protected function playbackError(Throwable $e, Party $party): JsonResponse
    {
        return match (true) {
            $e instanceof NoActiveDeviceException => $this->playbackFailure('no_device', 'No active playback device. Open Spotify on a device or pick one and try again.', 409),
            $e instanceof DeviceRestrictedException => $this->playbackFailure('device_restricted', 'That device cannot be controlled remotely.', 409),
            $e instanceof DeviceNotFoundException => $this->playbackFailure('device_not_found', 'That device is no longer available. Refresh the device list.', 422),
            $e instanceof ProviderPremiumRequiredException => $this->premiumRequired($party),
            $e instanceof ProviderRelinkRequiredException => $this->playbackFailure('relink_required', 'The Spotify link has expired and must be re-linked.', 409),
            $e instanceof ProviderBackoffException => $this->rateLimited($e),
            $e instanceof PlaybackBusyException => $this->playbackFailure('busy', 'Playback is already changing. Try again.', 409),
            $e instanceof NothingToPlayException => $this->playbackFailure('nothing_to_play', 'There is nothing to play.', 409),
            $e instanceof NotPlayableException => $this->playbackFailure('not_playable', 'That request cannot be played right now.', 422),
            default => throw $e,
        };
    }

    private function premiumRequired(Party $party): JsonResponse
    {
        Cache::put(StartPlayback::premiumFlagKey($party), true, StartPlayback::PREMIUM_FLAG_SECONDS);

        return $this->playbackFailure('premium_required', 'Spotify only allows remote control on Premium accounts.', 409);
    }

    private function rateLimited(ProviderBackoffException $e): JsonResponse
    {
        $retryAfter = max(1, $e->retryAfterSeconds());

        return response()->json([
            'message' => "Spotify is busy, try again in {$retryAfter} s.",
            'code' => 'rate_limited',
            'retryAfter' => $retryAfter,
        ], 503, ['Retry-After' => (string) $retryAfter]);
    }

    private function playbackFailure(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
