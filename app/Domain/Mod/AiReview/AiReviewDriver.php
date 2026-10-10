<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Mod\Data\ModContext;

enum AiReviewDriver: string
{
    case OpenAi = 'openai';
    case Jev = 'jev';

    public function provider(): string
    {
        return match ($this) {
            self::OpenAi => 'openai',
            self::Jev => 'typesafe',
        };
    }

    public function keySetting(): string
    {
        return match ($this) {
            self::OpenAi => 'openai_api_key',
            self::Jev => 'jev_api_key',
        };
    }

    public function apiKey(ModContext $context): string
    {
        return (string) ($context->settings[$this->keySetting()] ?? '');
    }

    public function model(): ?string
    {
        $model = config("mods.ai_review.models.{$this->value}");

        return is_string($model) && $model !== '' ? $model : null;
    }
}
