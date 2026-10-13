<?php

namespace App\Services\Requests\Rules;

use App\Enums\Purpose;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;

final class ReservedTrackRule implements RequestRule
{
    public function __construct(private readonly MusicProviderFactory $providers) {}

    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $provider = $this->providers->forParty($context->party, Purpose::Search);

        return $provider->isReservedTrack($track->id) ? RequestDecision::reject('This song is not available') : RequestDecision::allow();
    }
}
