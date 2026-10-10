<?php

use App\Domain\Mod\AiReview\AiRequestReviewMod;
use App\Domain\Mod\ArtistAlbumLimit\ArtistAlbumLimitMod;
use App\Domain\Mod\Variety\VarietyMod;

return [
    'registered' => [
        AiRequestReviewMod::class,
        ArtistAlbumLimitMod::class,
        VarietyMod::class,
    ],

    'ai_review' => [
        'models' => [
            'openai' => env('AI_REVIEW_OPENAI_MODEL'),
            'jev' => env('AI_REVIEW_JEV_MODEL', 'jev-latest'),
        ],
    ],
];
