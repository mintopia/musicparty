<?php

namespace App\Services\Playback;

use App\Enums\StartTargetKind;
use App\Models\UpcomingSong;
use App\Services\Music\Data\Track;

final readonly class StartTarget
{
    public function __construct(
        public StartTargetKind $kind,
        public Track $track,
        public int $positionMs,
        public ?UpcomingSong $request,
        public bool $alreadySent,
        public ?string $deviceId = null,
    ) {}
}
