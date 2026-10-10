<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Support\Metrics\CounterStore;
use Illuminate\Support\Facades\Cache;

readonly class RecordDroppedPlayerFrame
{
    public const string EXPIRED = 'expired';

    public const string OUT_OF_ORDER = 'out_of_order';

    public const array REASONS = [self::EXPIRED, self::OUT_OF_ORDER];

    public const string COUNTER_PREFIX = 'metrics.player_frames_dropped';

    private const int LOG_WINDOW_SECONDS = 60;

    public function __construct(private CounterStore $counters, private RecordPartyLogEntry $record) {}

    public function __invoke(string $partyCode, string $reason, int $sequence): void
    {
        $this->counters->increment(self::COUNTER_PREFIX.'.'.$reason);

        if (! Cache::add("player-frames-dropped-logged:{$partyCode}", true, self::LOG_WINDOW_SECONDS)) {
            return;
        }

        $party = Party::findByCode($partyCode);

        if ($party === null) {
            return;
        }

        ($this->record)($party, 'player.frames_dropped', details: [
            'reason' => $reason,
            'sequence' => $sequence,
        ], systemActor: 'player');
    }
}
