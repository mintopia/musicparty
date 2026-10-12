<?php

use App\Domain\Mod\AiReview\AiRequestReviewMod;
use App\Domain\Mod\ArtistAlbumLimit\ArtistAlbumLimitMod;
use App\Domain\Mod\Trust\TrustScoreMod;
use App\Domain\Mod\Variety\VarietyMod;
use App\Domain\Mod\Whamageddon\WhamageddonMod;

return [
    'registered' => [
        AiRequestReviewMod::class,
        ArtistAlbumLimitMod::class,
        TrustScoreMod::class,
        VarietyMod::class,
        WhamageddonMod::class,
    ],

    'ai_review' => [
        'models' => [
            'openai' => env('AI_REVIEW_OPENAI_MODEL'),
            'jev' => env('AI_REVIEW_JEV_MODEL', 'jev-latest'),
        ],
    ],
];
