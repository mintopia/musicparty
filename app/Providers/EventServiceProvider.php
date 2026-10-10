<?php

namespace App\Providers;

use App\Domain\Admin\Models\Setting;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Listeners\AppendStartedTrackToHistory;
use App\Domain\Party\Events\PartyLogEntryRecorded;
use App\Domain\Party\Events\PartyStateChanged;
use App\Domain\Party\Listeners\BroadcastPartyLogEntry;
use App\Domain\Party\Listeners\BroadcastPartyState;
use App\Domain\Playback\Listeners\HandlePlayerClientEvent;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\RequestDecisionRecorded;
use App\Domain\Queue\Events\TrackEnded;
use App\Domain\Queue\Events\TrackStarted;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Stats\Listeners\RefreshStatsOnPartyActivity;
use App\Observers\SettingObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\LaravelPassport\LaravelPassportExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\SpotifyExtendSocialite;
use SocialiteProviders\Steam\SteamExtendSocialite;
use SocialiteProviders\Twitch\TwitchExtendSocialite;

class EventServiceProvider extends ServiceProvider
{
    protected $observers = [
        User::class => UserObserver::class,
        Setting::class => SettingObserver::class,
    ];

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SocialiteWasCalled::class => [
            DiscordExtendSocialite::class.'@handle',
            SteamExtendSocialite::class.'@handle',
            TwitchExtendSocialite::class.'@handle',
            LaravelPassportExtendSocialite::class.'@handle',
            SpotifyExtendSocialite::class.'@handle',
        ],
        PartyLogEntryRecorded::class => [BroadcastPartyLogEntry::class],
        PartyStateChanged::class => [BroadcastPartyState::class],
        RequestCreated::class => [RefreshStatsOnPartyActivity::class],
        VoteCast::class => [RefreshStatsOnPartyActivity::class],
        TrackEnded::class => [RefreshStatsOnPartyActivity::class],
        TrackStarted::class => [AppendStartedTrackToHistory::class],
        RequestDecisionRecorded::class => [RefreshStatsOnPartyActivity::class],
        MessageReceived::class => [
            HandlePlayerClientEvent::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
