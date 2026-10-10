<?php

namespace App\Domain\Mod\Variety;

use App\Domain\Mod\Contracts\ScoreModifier;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Music\Support\NameNormaliser;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;

class VarietyScoreModifier implements ScoreModifier
{
    private bool $playingLoaded = false;

    private ?TrackRequest $playing = null;

    /** @var list<string> */
    private array $playingArtists = [];

    public function adjustment(ModContext $context, TrackRequest $request): int
    {
        $score = (int) $request->score;
        if ($score <= 0) {
            return 0;
        }

        $requesterPenalty = (int) ($context->settings['requester_penalty_percent'] ?? 0);
        $artistPenalty = (int) ($context->settings['artist_penalty_percent'] ?? 0);
        if ($requesterPenalty <= 0 && $artistPenalty <= 0) {
            return 0;
        }

        $playing = $this->playing($context);
        if ($playing === null) {
            return 0;
        }

        $factor = 1.0;
        if ($requesterPenalty > 0 && $request->party_member_id !== null && $request->party_member_id === $playing->party_member_id) {
            $factor *= 1 - min($requesterPenalty, 100) / 100;
        }
        if ($artistPenalty > 0 && array_intersect(NameNormaliser::normaliseAll($request->artists), $this->playingArtists) !== []) {
            $factor *= 1 - min($artistPenalty, 100) / 100;
        }

        $adjusted = max(1, (int) round($score * $factor));

        return $adjusted - $score;
    }

    private function playing(ModContext $context): ?TrackRequest
    {
        if (! $this->playingLoaded) {
            $this->playingLoaded = true;
            $this->playing = TrackRequest::query()
                ->where('party_id', $context->party->id)
                ->where('status', RequestStatus::Playing)
                ->first();
            $this->playingArtists = NameNormaliser::normaliseAll($this->playing?->artists);
        }

        return $this->playing;
    }
}
