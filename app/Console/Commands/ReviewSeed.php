<?php

namespace App\Console\Commands;

use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use Database\Seeders\Review\ReviewHarnessSeeder;
use Illuminate\Console\Command;

class ReviewSeed extends Command
{
    protected $signature = 'review:seed {--quiet-parties : Mark every party inactive so the Admin dashboard shows its no-live-parties state}
        {--all-clear : Clear the re-link and missing-secret fixtures so the Admin dashboard has nothing needing attention}';

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

        if ($this->option('all-clear')) {
            LinkedAccount::query()->whereNotNull('needs_relink_at')->update(['needs_relink_at' => null]);
            ProviderSetting::query()->where('validation', 'like', '%required%')->get()
                ->filter(fn (ProviderSetting $setting): bool => trim((string) $setting->value) === '')
                ->each(function (ProviderSetting $setting): void {
                    $setting->value = 'review-placeholder';
                    $setting->save();
                });
        }

        $this->components->info('Review fixtures seeded.');

        return self::SUCCESS;
    }
}
