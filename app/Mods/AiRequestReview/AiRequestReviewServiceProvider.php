<?php

namespace App\Mods\AiRequestReview;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AiRequestReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AiRequestReviewRule::class);
        $this->app->scoped(PinnedClientOptions::class);
    }

    public function boot(): void
    {
        Http::globalOptions(fn (): array => $this->app->make(PinnedClientOptions::class)->current());
    }
}
