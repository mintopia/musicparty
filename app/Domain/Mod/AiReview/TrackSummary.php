<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Queue\Models\TrackRequest;

final readonly class TrackSummary
{
    /**
     * @param  list<string>  $artists
     */
    public function __construct(
        public string $title,
        public array $artists,
        public string $album,
        public bool $explicit,
    ) {}

    public static function fromRequest(TrackRequest $request): self
    {
        return new self($request->title, $request->artists, (string) $request->album, (bool) $request->explicit);
    }

    /**
     * @return array{title: string, artists: list<string>, album: string, explicit: bool}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'artists' => $this->artists, 'album' => $this->album, 'explicit' => $this->explicit];
    }
}
