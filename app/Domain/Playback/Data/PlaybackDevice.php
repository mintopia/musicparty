<?php

namespace App\Domain\Playback\Data;

readonly class PlaybackDevice
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public bool $isActive,
    ) {}

    /**
     * @return array{id: string, name: string, type: string, is_active: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'is_active' => $this->isActive];
    }
}
