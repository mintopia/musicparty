<?php

namespace App\Services\Requests;

use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Track;

final readonly class TrackAnnotation
{
    public function __construct(
        public Track $track,
        public bool $upcoming,
        public ?int $upcomingId,
        public int $score,
        public ?int $vote,
        public ?string $user,
        public bool $allowed,
        public ?string $reason,
        public ?string $externalUrl = null,
        public string $providerName = '',
        public bool $flagged = false,
        public bool $awaitingApproval = false,
        /** @var array<string, string>|null */
        public ?array $decoration = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSearchResult(): array
    {
        $track = $this->track;

        return [
            'id' => $track->id,
            'name' => $track->name,
            'duration_ms' => $track->durationMs,
            'explicit' => $track->explicit,
            'popularity' => $track->popularity,
            'external_url' => $this->externalUrl,
            'provider_name' => $this->providerName,
            'album' => [
                'id' => $track->album->id,
                'name' => $track->album->name,
                'images' => $track->album->imageUrl === null ? [] : [['url' => $track->album->imageUrl]],
            ],
            'artists' => array_map(fn (ArtistRef $artist): array => ['id' => $artist->id, 'name' => $artist->name], $track->artists),
            'upcoming' => $this->upcoming,
            'upcoming_id' => $this->upcomingId,
            'score' => $this->score,
            'vote' => $this->vote,
            'user' => $this->user,
            'allowed' => $this->allowed,
            'reason' => $this->reason,
        ];
    }
}
