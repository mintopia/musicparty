<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\PartyLibrespotRequest;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use Illuminate\Support\Facades\Log;

class PartyController extends Controller
{
    public function librespot(PartyLibrespotRequest $request, Party $party)
    {
        switch ($request->input('event')) {
            case 'started':
            case 'playing':
            case 'paused':
            case 'stopped':
                Log::debug("{$party}: Received librespot webhook event: {$request->input('event')}");
                PartyUpdate::dispatch($party)->afterResponse();

                break;
            default:
                break;
        }
        return response()->noContent();
    }

    public function simple(Party $party)
    {
        Log::debug("{$party}: Received simple webhook event");
        if (config('musicparty.dispatch_webhook_after_request')) {
            Log::debug("{$party}: Dispatching PartyUpdate after request");
            PartyUpdate::dispatch($party)->afterResponse();
        } else {
            Log::debug("{$party}: Dispatching PartyUpdate");
            PartyUpdate::dispatch($party);
        }
        return response()->noContent();
    }
}
