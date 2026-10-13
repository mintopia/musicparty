<?php

namespace App\Providers;

use App\Enums\Purpose;
use App\Listeners\MessageLoggedListener;
use App\Models\Party;
use App\Models\Setting;
use App\Models\SongRating;
use App\Models\Theme;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Observers\PartyObserver;
use App\Observers\SettingObserver;
use App\Observers\SongRatingObserver;
use App\Observers\ThemeObserver;
use App\Observers\UpcomingSongObserver;
use App\Observers\UserObserver;
use App\Observers\VoteObserver;
use App\Services\ActiveTheme;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\ModSettingsStore;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PlayedSongAugmentService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Random\Randomizer;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\LaravelPassport\LaravelPassportExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\SpotifyExtendSocialite;
use SocialiteProviders\Steam\SteamExtendSocialite;
use SocialiteProviders\Twitch\TwitchExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveTheme::class);
        $this->app->bind(UpcomingSongAugmentService::class);
        $this->app->bind(PlayedSongAugmentService::class);
        $this->app->scoped(ModSettingsStore::class);
        $this->app->scoped(FakeMusicProvider::class);
        $this->app->scoped(MusicProviderFactory::class, function (): MusicProviderFactory {
            $fake = fn (User $user, Purpose $purpose, ?string $market = null): FakeMusicProvider => $this->app->make(FakeMusicProvider::class);

            return new MusicProviderFactory([
                'spotify' => $this->app->environment('testing')
                    ? $fake
                    : fn (User $user, Purpose $purpose, ?string $market = null): SpotifyMusicProvider => SpotifyMusicProvider::make($user, $purpose, $market),
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
        User::observe(UserObserver::class);
        Setting::observe(SettingObserver::class);
        Theme::observe(ThemeObserver::class);
        Party::observe(PartyObserver::class);
        Vote::observe(VoteObserver::class);
        UpcomingSong::observe(UpcomingSongObserver::class);
        SongRating::observe(SongRatingObserver::class);

        Event::listen(SocialiteWasCalled::class, DiscordExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, SteamExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, TwitchExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, LaravelPassportExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, SpotifyExtendSocialite::class.'@handle');
        Event::listen(MessageLogged::class, MessageLoggedListener::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        Blade::directive('setting', fn (string $expression, $default = null) => "<?php echo App\Models\Setting::fetch($expression, $default); ?>");

        view()->composer(['app', 'layouts.app', 'layouts.login', 'parties.tv', 'parties.ytplayer'], function ($view) {
            $activeTheme = $this->app->make(ActiveTheme::class);
            $view->with('currentTheme', $activeTheme->current());
            $view->with('darkMode', $activeTheme->darkMode());
        });

        Pulse::user(fn ($user) => [
            'name' => $user->nickname,
            'extra' => $user->getEmail() ?? '',
            'avatar' => $user->avatarUrl() ?? '',
        ]);
    }
}
