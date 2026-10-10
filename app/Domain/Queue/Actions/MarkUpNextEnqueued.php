<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Models\TrackRequest;

readonly class MarkUpNextEnqueued
{
    public function __invoke(TrackRequest $request): TrackRequest
    {
        $request->forceFill(['enqueued_at' => now()])->save();

        return $request;
    }
}
