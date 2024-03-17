<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\DiscordApi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, ToString;

    protected $casts = [
        'terms_agreed_at' => 'datetime',
        'last_login' => 'datetime',
        'status_updated_at' => 'datetime',
        'status' => 'object',
    ];

    protected ?string $email = null;
    protected ?SpotifyWebAPI $api = null;
    protected ?Session $session = null;
    protected ?array $playlists = null;
    protected ?array $devices = null;
    protected ?object $recentTracks = null;
    protected array $playlistCache = [];
    protected $hasUpdatedStatus = false;

    /**
     * @return HasMany<LinkedAccount>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    /**
     * @return HasMany<Party>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function partyMembers(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(SongRating::class);
    }

    public function hasRole(string|Role $role): bool
    {
        if ($role instanceof Role) {
            $role = $role->code;
        }
        return (bool)$this->roles()->whereCode($role)->count();
    }

    public function getEmail(): ?string
    {
        if ($this->email !== null) {
            return $this->email;
        }
        $linked = $this->accounts()->whereNotNull('email')->first();
        if ($linked) {
            $this->email = $linked->email;
            return $this->email;
        }
        return null;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function avatarUrl(): string
    {
        foreach ($this->accounts as $acc) {
            if ($acc->avatar_url) {
                return $acc->avatar_url;
            }
        }
        if ($email = $this->getEmail()) {
            $toHash = $email;
        } else {
            $toHash = $this->nickname;
        }
        $hash = hash('sha256', $toHash);
        return "https://gravatar.com/avatar/{$hash}?d=retro";
    }

    protected function toStringName(): string
    {
        return $this->nickname;
    }

    public function hasSpotifyLinks(): bool
    {
        $count = $this->accounts()->whereHas('provider', function ($query) {
            $query->whereIn('code', ['spotify', 'spotifysearch']);
        })->count();
        return $count === 2;
    }

    public function getSpotifyApi(): ?SpotifyWebAPI
    {
        /**
         * @var LinkedAccount $account
         */
        $account = $this->accounts()->whereHas('provider', function ($query) {
            $query->whereCode('spotify');
        })->first();
        if (!$account) {
            return null;
        }

        if ($this->session === null) {
            $clientId = $account->provider->getSetting('client_id');
            $clientSecret = $account->provider->getSetting('client_secret');
            $this->session = new Session($clientId, $clientSecret);
            $this->session->setAccessToken($account->access_token);
        }

        if (!$this->api) {
            // Create new API
            Log::debug("{$this}: Creating new API connection");
            $request = new Request();
            $this->api = new SpotifyWebAPI([], $this->session, $request);
        }


        if ($account->access_token_expires_at < now()->addMinutes(5)) {
            Log::debug("{$this}: Refreshing expiring access token");
            $this->session->refreshAccessToken($account->refresh_token);
            $account->access_token = $this->session->getAccessToken();
            $account->access_token_expires_at = new Carbon($this->session->getTokenExpiration());
            $account->save();
        }

        return $this->api;
    }

    public function getDevices(): array
    {
        if ($this->devices !== null) {
            return $this->devices;
        }
        $api = $this->getSpotifyApi();
        if (!$api) {
            return [];
        }

        $this->devices = Cache::remember("users.{$this->id}.devices", 300, function() use ($api) {
            Log::debug("{$this}: Spotify API -> getMyDevices()");
            return $api->getMyDevices()->devices;
        });
        return $this->devices;
    }

    public function getPlaylists(): array
    {
        if ($this->playlists !== null) {
            return $this->playlists;
        }
        $api = $this->getSpotifyApi();
        if (!$api) {
            return [];
        }

        $this->playlists = [];
        $offset = 0;
        do {
            Log::debug("{$this}: Spotify API -> getMyPlaylists({$offset})");
            $result = $api->getMyPlaylists(['limit' => 50, 'offset' => $offset]);
            $this->playlists = array_merge($this->playlists, $result->items);
            $offset += 50;
        } while ($result->next);
        return $this->playlists;
    }

    public function getSpotifyStatus(bool $forced = false): ?object
    {
        if ($this->hasUpdatedStatus && !$forced) {
            return $this->status;
        }
        Log::debug("{$this}: Spotify API -> getMyCurrentPlaybackInfo()");
        $this->status = $this->getSpotifyApi()->getMyCurrentPlaybackInfo([
            'market' => $this->market,
        ]);
        $this->status_updated_at = Carbon::now();
        $this->save();
        return $this->status;
    }

    public function getPartyMember(Party $party): ?PartyMember
    {
        foreach($this->partyMembers as $member) {
            if ($member->party_id === $party->id) {
                return $member;
            }
        }
        return null;
    }

    public function getRecentTracks(bool $force = false): ?object
    {
        if (!$force && $this->recentTracks !== null) {
            return $this->recentTracks;
        }

        Log::debug("{$this}: Spotify API -> getMyRecentTracks()");
        $this->recentTracks = $this->getSpotifyApi()->getMyRecentTracks([
            'limit' => 20,
            'market' => $this->market,
        ]);
        return $this->recentTracks;
    }

    public function getPlaylist(string $id, bool $force = false): ?object
    {
        if (!$force && isset($this->playlistCache[$id])) {
            return $this->playlistCache[$id];
        }

        Log::debug("{$this}: Spotify API -> getPlaylist({$id})");
        $this->playlistCache[$id] = $this->getSpotifyApi()->getPlaylist($id, [
            'market' => $this->market,
        ]);
        return $this->playlistCache[$id];
    }
}
