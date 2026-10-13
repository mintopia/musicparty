<?php

namespace App\Mods;

final readonly class TrackDecoration
{
    public function __construct(
        public ?string $displayName = null,
        public ?string $label = null,
        public ?string $cssClass = null,
        public ?string $accent = null,
    ) {}

    /**
     * @return array{displayName?: string, label?: string, class?: string, accent?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'displayName' => $this->displayName,
            'label' => $this->label,
            'class' => $this->cssClass,
            'accent' => $this->accent,
        ], fn (?string $value): bool => $value !== null);
    }
}
