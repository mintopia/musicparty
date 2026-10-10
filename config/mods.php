<?php

use App\Domain\Mod\AiReview\AiRequestReviewMod;

return [
    'registered' => [
        AiRequestReviewMod::class,
    ],

    'ai_review' => [
        'models' => [
            'openai' => env('AI_REVIEW_OPENAI_MODEL'),
            'jev' => env('AI_REVIEW_JEV_MODEL', 'jev-latest'),
        ],
    ],
];
