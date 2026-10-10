<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;

readonly class MarkUpNextEnqueueUnconfirmed
{
    public function __invoke(TrackRequest $request): bool
    {
        return TrackRequest::query()
            ->whereKey($request->id)
            ->where('status', RequestStatus::UpNext)
            ->whereNotNull('enqueued_at')
            ->update(['enqueue_unconfirmed' => true]) > 0;
    }
}
