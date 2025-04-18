<?php

namespace App\Providers;

use App\Models\Theme;
use App\Services\PlayedSongAugmentService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UpcomingSongAugmentService::class);
        $this->app->bind(PlayedSongAugmentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('setting', function (string $expression, $default = null) {
            return "<?php echo App\Models\Setting::fetch($expression, $default); ?>";
        });

        view()->composer(['layouts.app', 'layouts.login', 'parties.tv'], function ($view) {
                $currentTheme = Theme::whereActive(true)->first();
                $darkMode = false;
            if ($currentTheme) {
                $darkMode = $currentTheme->dark_mode;
            }
                $view->with('currentTheme', $currentTheme);
                $view->with('darkMode', $darkMode);
        });
    }
}
