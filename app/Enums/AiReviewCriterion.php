<?php

namespace App\Enums;

enum AiReviewCriterion: string
{
    case None = 'none';
    case Guidelines = 'guidelines';
    case Explicit = 'explicit';
    case Vibe = 'vibe';
    case Troll = 'troll';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Fits the party',
            self::Guidelines => 'Guidelines',
            self::Explicit => 'Explicit',
            self::Vibe => 'Vibe',
            self::Troll => 'Troll',
            self::Other => 'Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'Acceptable, fits the party',
            self::Guidelines => 'Breaks the party guidelines',
            self::Explicit => 'Explicit or offensive content beyond what the party allows',
            self::Vibe => 'Genre or vibe clashes with what the party has been playing and rating',
            self::Troll => 'Joke, meme or troll request',
            self::Other => 'Unsuitable for another reason',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        $definitions = [];
        foreach (self::cases() as $case) {
            $definitions[$case->value] = $case->description();
        }

        return $definitions;
    }
}
