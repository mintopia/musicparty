<?php
namespace App\Http\Controllers\Webhooks;

use App\Models\Party;
use App\Requests\Webhooks\PartyLibrespotRequest;

class PartyController extends Controller
{
    public function librespot(PartyLibrespotRequest $request, Party $party)
    {
        switch ($request->input('event')) {
            case 'started':
            case 'playing':
                // TODO: Dispatch Event
                Log::debug("{$party}: Received librespot webhook for {$request->input('event')}");
                break;
            
            case 'paused':
            case 'stopped':
                Log::debug("{$party}: Received librespot webhook for {$request->input('event')}");
                // TODO: Dispatch Event
                break;
            
            default:
                break;
        }
        return response()->noContent();
    }
}