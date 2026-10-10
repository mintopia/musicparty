<?php

use App\Domain\Mod\AiReview\AiRequestReviewMod;
use App\Domain\Mod\ArtistAlbumLimit\ArtistAlbumLimitMod;

return [
    'registered' => [
        AiRequestReviewMod::class,
        ArtistAlbumLimitMod::class,
    ],

    'ai_review' => [
        'models' => [
            'openai' => env('AI_REVIEW_OPENAI_MODEL'),
            'jev' => env('AI_REVIEW_JEV_MODEL', 'jev-latest'),
        ],
    ],
];
