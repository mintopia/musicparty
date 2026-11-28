<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\PartyLibrespotRequest;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\PlaybackSync;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PartyController extends Controller
{
    public const int PENDING_GUARD_SECONDS = 3;

    protected function dispatchUpdate(Party $party, bool $afterResponse = false): void
    {
        if (! Cache::add($party->webhookPendingKey(), true, self::PENDING_GUARD_SECONDS)) {
            Log::debug("{$party}: PartyUpdate already pending, skipping dispatch");

            return;
        }

        $pending = PartyUpdate::dispatch($party, PlaybackSource::Webhook);
        if ($afterResponse) {
            $pending->afterResponse();
        }
    }

    public function librespot(PartyLibrespotRequest $request, Party $party): Response
    {
        switch ($request->input('event')) {
            case 'started':
            case 'playing':
            case 'paused':
            case 'stopped':
                Log::debug("{$party}: Received librespot webhook event: {$request->input('event')}");
                $this->dispatchUpdate($party, true);

                break;
            default:
                break;
        }

        return response()->noContent();
    }

    public function simple(Party $party): Response
    {
        Log::debug("{$party}: Received simple webhook event");
        if (config('musicparty.webhook_should_queue')) {
            if (config('musicparty.webhook_dispatch_after_request')) {
                Log::debug("{$party}: Dispatching PartyUpdate after request");
                $this->dispatchUpdate($party, true);
            } else {
                Log::debug("{$party}: Dispatching PartyUpdate");
                $this->dispatchUpdate($party);
            }
        } else {
            Log::debug("{$party}: Updating party directly from webhook");
            app(PlaybackSync::class)->sync($party, PlaybackSource::Webhook);
        }

        return response()->noContent();
    }

    public function soloist(Party $party): Response
    {
        Log::debug("{$party}: Received soloist webhook event");
        $this->dispatchUpdate($party);

        return response()->noContent();
    }
}
