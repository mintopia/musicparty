<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Models\TrackRequest;

readonly class ConfirmUpNextEnqueued
{
    public function __invoke(TrackRequest $request): TrackRequest
    {
        $request->forceFill(['enqueue_unconfirmed' => false])->save();

        return $request;
    }
}
