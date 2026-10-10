<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Models\TrackRequest;

readonly class ClearUpNextEnqueued
{
    public function __invoke(TrackRequest $request): TrackRequest
    {
        $request->forceFill(['enqueued_at' => null, 'enqueue_unconfirmed' => false])->save();

        return $request;
    }
}
