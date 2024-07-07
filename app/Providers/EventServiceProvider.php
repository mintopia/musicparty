<?php

namespace App\Providers;

use App\Listeners\MessageLoggedListener;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\EmailAddress;
use App\Models\Event;
use App\Models\Party;
use App\Models\Seat;
use App\Models\SeatingPlan;
use App\Models\Setting;
use App\Models\SongRating;
use App\Models\Theme;
use App\Models\Ticket;
use App\Models\TicketProvider;
use App\Models\TicketType;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Observers\ClanMembershipObserver;
use App\Observers\ClanObserver;
use App\Observers\EmailAddressObserver;
use App\Observers\EventObserver;
use App\Observers\PartyObserver;
use App\Observers\SeatingPlanObserver;
use App\Observers\SeatObserver;
use App\Observers\SettingObserver;
use App\Observers\SongRatingObserver;
use App\Observers\ThemeObserver;
use App\Observers\TicketObserver;
use App\Observers\TicketProviderObserver;
use App\Observers\TicketTypeObserver;
use App\Observers\UpcomingSongObserver;
use App\Observers\UserObserver;
use App\Observers\VoteObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
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
        Theme::class => ThemeObserver::class,
        Party::class => PartyObserver::class,
        Vote::class => VoteObserver::class,
        UpcomingSong::class => UpcomingSongObserver::class,
        SongRating::class => SongRatingObserver::class,
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
            DiscordExtendSocialite::class . '@handle',
            SteamExtendSocialite::class . '@handle',
            TwitchExtendSocialite::class . '@handle',
            LaravelPassportExtendSocialite::class . '@handle',
            SpotifyExtendSocialite::class.'@handle',
        ],
        MessageLogged::class => [
            MessageLoggedListener::class,
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
