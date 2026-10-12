<?php

namespace App\Http\Middleware;

use App\Models\Party;
use App\Support\LeakyBucket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleWebhookPerParty
{
    public function handle(Request $request, Closure $next): Response
    {
        $party = $request->route('party');
        $partyKey = $party instanceof Party ? $party->getKey() : (string) $party;

        $bucket = new LeakyBucket(
            "webhook:{$partyKey}",
            (int) config('musicparty.webhook_burst'),
            (float) config('musicparty.webhook_leak_per_second'),
        );

        if (! $bucket->attempt()) {
            return response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $next($request);
    }
}
