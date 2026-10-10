<?php

namespace App\Domain\Stats\Listeners;

use App\Domain\Queue\Events\RatingCast;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Queue\VoteDirection;
use App\Support\Metrics\CounterStore;

readonly class CountPartyActivity
{
    public const string REQUESTS = 'requests';

    public const string VOTES = 'votes';

    public const string RATINGS = 'ratings';

    public const string MEMBER = 'member';

    public const string SYSTEM = 'system';

    public const string LIKE = 'like';

    public const string DISLIKE = 'dislike';

    public const string SERIES_SET = 'metrics.party_activity.series';

    public function __construct(private CounterStore $counters) {}

    public function handle(RequestCreated|VoteCast|RatingCast $event): void
    {
        match (true) {
            $event instanceof RequestCreated => $this->count(self::REQUESTS, $event->party->code, $event->request->party_member_id === null ? self::SYSTEM : self::MEMBER),
            $event instanceof VoteCast => $event->direction === null ? null : $this->count(self::VOTES, $event->party->code, $event->direction->value),
            $event instanceof RatingCast => $this->count(self::RATINGS, $event->party->code, $event->direction === VoteDirection::Up ? self::LIKE : self::DISLIKE),
        };
    }

    public static function counterKey(string $metric, string $partyCode, string $label): string
    {
        return "metrics.party_activity.{$metric}.{$partyCode}.{$label}";
    }

    private function count(string $metric, string $partyCode, string $label): void
    {
        $this->counters->increment(self::counterKey($metric, $partyCode, $label));
        $this->counters->addMember(self::SERIES_SET, "{$metric}|{$partyCode}|{$label}");
    }
}
