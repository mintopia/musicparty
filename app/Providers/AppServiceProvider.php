<?php

namespace App\Providers;

use App\Enums\Purpose;
use App\Models\Theme;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\ModSettingsStore;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PlayedSongAugmentService;
use App\Services\Spotify\SpotifyGateway;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Random\Randomizer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UpcomingSongAugmentService::class);
        $this->app->bind(PlayedSongAugmentService::class);
        $this->app->scoped(ModSettingsStore::class);
        $this->app->scoped(FakeMusicProvider::class);
        $this->app->scoped(MusicProviderFactory::class, function (): MusicProviderFactory {
            $fake = fn (User $user, Purpose $purpose): FakeMusicProvider => $this->app->make(FakeMusicProvider::class);

            return new MusicProviderFactory([
                'spotify' => $this->app->environment('testing')
                    ? $fake
                    : fn (User $user, Purpose $purpose): SpotifyMusicProvider => new SpotifyMusicProvider($this->app->make(SpotifyGateway::class), $user, $purpose),
                'fake' => $fake,
            ]);
        });
        if ($this->app->environment('testing')) {
            $this->app->bind(MusicProvider::class, FakeMusicProvider::class);
            $this->app->bind(ProviderPlayback::class, FakeMusicProvider::class);
        }
        $this->app->bind(Randomizer::class, fn () => new Randomizer);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        Blade::directive('setting', fn (string $expression, $default = null) => "<?php echo App\Models\Setting::fetch($expression, $default); ?>");

        view()->composer(['layouts.app', 'layouts.login', 'parties.tv', 'parties.ytplayer'], function ($view) {
            $currentTheme = Theme::whereActive(true)->first();
            $darkMode = false;
            if ($currentTheme) {
                $darkMode = $currentTheme->dark_mode;
            }
            $view->with('currentTheme', $currentTheme);
            $view->with('darkMode', $darkMode);
        });

        Pulse::user(fn ($user) => [
            'name' => $user->nickname,
            'extra' => $user->getEmail() ?? '',
            'avatar' => $user->avatarUrl() ?? '',
        ]);
    }
}
