<?php

namespace App\Mods\AiRequestReview;

use Illuminate\Support\ServiceProvider;

class AiRequestReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AiRequestReviewRule::class);
    }
}
