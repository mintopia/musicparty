<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Services\DiscordApi;
use App\Services\SpotifySearchService;
use App\Services\UpcomingSongAugmentService;
use GuzzleHttp\Client;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('setting', function(string $expression, $default = null) {
            return "<?php echo App\Models\Setting::fetch($expression, $default); ?>";
        });

        view()->composer(['layouts.app', 'layouts.login'],function($view) {
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
