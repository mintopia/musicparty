<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\User as SocialiteUser;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\Session;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $nickname
 * @property string $discord_name
 * @property string $email
 * @property string|null $avatar
 * @property string $discord_id
 * @property mixed $discord_access_token
 * @property mixed $discord_refresh_token
 * @property \Illuminate\Support\Carbon $discord_token_expires_at
 * @property string|null $spotify_id
 * @property mixed|null $spotify_access_token
 * @property mixed|null $spotify_refresh_token
 * @property \Illuminate\Support\Carbon|null $spotify_token_expires_at
 * @property int $can_create_party
 * @property int $admin
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCanCreateParty($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDiscordAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDiscordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDiscordName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDiscordRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDiscordTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSpotifyAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSpotifyRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSpotifyTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Party[] $parties
 * @property-read int|null $parties_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Vote[] $votes
 * @property-read int|null $votes_count
 * @property string|null $status
 * @property string|null $status_updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatusUpdatedAt($value)
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected ?SpotifyWebAPI $api = null;
    protected ?Session $session = null;

    public string $market = 'GB';

    protected $hidden = [
        'discord_refresh_token',
        'discord_access_token',
        'spotify_refresh_token',
        'spotify_access_token',
    ];

    protected $casts = [
        'discord_token_expires_at' => 'datetime',
        'discord_refresh_token' => 'encrypted',
        'discord_access_token' => 'encrypted',
        'spotify_token_expires_at' => 'datetime',
        'spotify_refresh_token' => 'encrypted',
        'spotify_access_token' => 'encrypted',
        'status' => 'object',
        'status_updated_at' => 'datetime',
    ];

    public static function fromDiscord(SocialiteUser $discordUser): User
    {
        $user = User::where('discord_id', $discordUser->id)->first();
        if (!$user) {
            $user = new User;
            $user->discord_id = $discordUser->id;
            if (User::all()->count() == 0) {
                $user->admin = true;
                $user->can_create_party = true;
            }
        }
        $user->discord_name = $discordUser->nickname;
        $user->nickname = $discordUser->name;
        $user->email = $discordUser->email;
        $user->avatar = $discordUser->avatar;
        $user->discord_access_token = $discordUser->token;
        $user->discord_refresh_token = $discordUser->refreshToken;
        $user->discord_token_expires_at = Carbon::now()->addSeconds($discordUser->expiresIn);
        $user->save();
        return $user;
    }

    public function updateFromSpotify(SocialiteUser $spotifyUser): User
    {
        $this->spotify_id = $spotifyUser->id;
        $this->spotify_access_token = $spotifyUser->token;
        $this->spotify_refresh_token = $spotifyUser->refreshToken;
        $this->spotify_token_expires_at = Carbon::now()->addSeconds($spotifyUser->expiresIn);
        $this->save();
        return $this;
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function parties()
    {
        return $this->hasMany(Party::class);
    }

    public function getSpotifyApi()
    {
        $cutoff = Carbon::now()->addMinutes(5);
        if ($this->api) {
            if ($this->spotify_token_expires_at < $cutoff) {
                Log::debug("[User:{$this->id}] Refreshing expiring access token");
                $this->session->refreshAccessToken($this->spotify_refresh_token);
                $this->spotify_access_token = $this->session->getAccessToken();
                $this->spotify_token_expires_at = new Carbon($this->session->getTokenExpiration());
                $this->save();
            }
            return $this->api;
        }

        $session = new Session(
            config('services.spotify.client_id'),
            config('services.spotify.client_secret'),
        );

        if ($this->spotify_token_expires_at < $cutoff) {
            Log::debug("[User:{$this->id}] Refreshing expiring access token");
            $session->refreshAccessToken($this->spotify_refresh_token);
            $this->spotify_access_token = $session->getAccessToken();
            $this->spotify_token_expires_at = new Carbon($session->getTokenExpiration());
            $this->save();
        } else {
            $session->setAccessToken($this->spotify_access_token);
        }

        Log::debug("[User:{$this->id}] Creating new API connection");
        $this->api = new SpotifyWebAPI([], $session);
        return $this->api;
    }

    public function getPlaylists(): array
    {
        $offset = 0;
        $playlists = [];
        do {
            $response = $this->getSpotifyApi()->getMyPlaylists([
                'limit' => 50,
                'offset' => $offset,
            ]);
            $playlists = array_merge($playlists, $response->items);
            $offset += 50;
        } while ($response->next !== null);
        return $playlists;
    }

    public function getSpotifyStatus()
    {
        $this->status = $this->getSpotifyApi()->getMyCurrentPlaybackInfo();
        $this->status_updated_at = Carbon::now();
        $this->save();
        return $this->status;
    }
}
