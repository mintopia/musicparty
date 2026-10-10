<?php

namespace App\Domain\Mod\AiReview;

final readonly class ReviewScore
{
    public function __construct(public float $normalized, public ?float $confidence = null) {}

    public function percent(): int
    {
        return (int) round($this->normalized * 100);
    }
}
