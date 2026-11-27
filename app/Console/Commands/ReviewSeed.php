<?php

namespace App\Console\Commands;

use App\Models\Party;
use Database\Seeders\Review\ReviewHarnessSeeder;
use Illuminate\Console\Command;

class ReviewSeed extends Command
{
    protected $signature = 'review:seed {--quiet-parties : Mark every party inactive so the Admin dashboard shows its no-live-parties state}';

    protected $description = 'Seed the review-only fixtures used to reproduce design-critique mockup frames (never runs in production)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->error('review:seed refuses to run in production.');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => ReviewHarnessSeeder::class, '--force' => true]);

        if ($this->option('quiet-parties')) {
            Party::query()->update(['active' => false]);
        }

        $this->components->info('Review fixtures seeded.');

        return self::SUCCESS;
    }
}
