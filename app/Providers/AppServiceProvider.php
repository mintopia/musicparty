<?php

namespace App\Providers;

use App\Console\Review\ReviewSeed;
use App\Enums\Purpose;
use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Listeners\BroadcastWebPlayerToken;
use App\Listeners\DispatchPushedPlaybackState;
use App\Listeners\MessageLoggedListener;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Setting;
use App\Models\SongRating;
use App\Models\Theme;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Observers\PartyObserver;
use App\Observers\PlayedSongObserver;
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
use App\Services\Playback\EncoreStateMapper;
use App\Services\Playback\PushedStateRouter;
use App\Services\Playback\WebPlayerStateMapper;
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
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;
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
        $this->app->tag([WebPlayerStateMapper::class], 'pushed-state-mappers');
        $this->app->bind(PushedStateRouter::class, fn (): PushedStateRouter => new PushedStateRouter($this->app->tagged('pushed-state-mappers')));
        $this->app->tag(EncoreStateMapper::class, 'pushed-state-mappers');
        $this->app->bind(UpcomingSongAugmentService::class);
        $this->app->bind(PlayedSongAugmentService::class);
        $this->app->scoped(ModSettingsStore::class);
        $fakeAllowed = MusicProviderFactory::fakeAllowed();
        if ($fakeAllowed) {
            $this->app->scoped(FakeMusicProvider::class);
        }
        $this->app->scoped(MusicProviderFactory::class, function () use ($fakeAllowed): MusicProviderFactory {
            $registry = ['spotify' => SpotifyMusicProvider::make(...)];
            if ($fakeAllowed) {
                $fake = fn (User $user, Purpose $purpose, ?string $market = null): FakeMusicProvider => FakeMusicProvider::forUser($user, $this->app->make(FakeMusicProvider::class));
                $registry['fake'] = $fake;
                if ($this->app->environment('testing')) {
                    $registry['spotify'] = $fake;
                }
            }

            return new MusicProviderFactory($registry);
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
        if (! $this->app->environment('production')) {
            $this->commands([ReviewSeed::class]);
        }

        User::observe(UserObserver::class);
        Setting::observe(SettingObserver::class);
        Theme::observe(ThemeObserver::class);
        Party::observe(PartyObserver::class);
        Vote::observe(VoteObserver::class);
        UpcomingSong::observe(UpcomingSongObserver::class);
        SongRating::observe(SongRatingObserver::class);
        PlayedSong::observe(PlayedSongObserver::class);

        Event::listen(SocialiteWasCalled::class, DiscordExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, SteamExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, TwitchExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, LaravelPassportExtendSocialite::class.'@handle');
        Event::listen(SocialiteWasCalled::class, SpotifyExtendSocialite::class.'@handle');
        Event::listen(MessageLogged::class, MessageLoggedListener::class);
        Event::listen(SpotifyAccessTokenUpdatedEvent::class, BroadcastWebPlayerToken::class);
        Event::listen(MessageReceived::class, DispatchPushedPlaybackState::class);
        Event::listen(ChannelRemoved::class, [DispatchPushedPlaybackState::class, 'handleChannelRemoved']);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('playback', fn (Request $request) => Limit::perMinute(30)->by(($request->user()?->id ?: $request->ip()).':'.($request->route('party') instanceof Party ? $request->route('party')->code : '')));

        Blade::directive('setting', fn (string $expression, $default = null) => "<?php echo App\Models\Setting::fetch($expression, $default); ?>");

        view()->composer('app', function ($view) {
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
