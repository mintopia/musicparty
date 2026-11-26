<?php

namespace App\Services\Dashboard;

use App\Enums\Purpose;
use App\Enums\StartTargetKind;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use App\Services\Playback\ContextPlaylist;
use App\Services\Playback\PartyDashboardCache;
use App\Services\Playback\StartPlayback;
use App\Services\Playback\StartTarget;
use App\Services\PlaybackControl;
use App\Services\PlaybackStatus;
use App\Services\Requests\FlaggedRequests;
use App\Services\UpcomingSongAugmentService;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Builds the props for the Party Dashboard page; shapes mirror DashboardProps in resources/js/types.ts.
 */
class PartyDashboard
{
    private const int UP_NEXT_SIZE = 3;

    private const int UPCOMING_SIZE = 10;

    private const int FLAGGED_SIZE = 5;

    private const int READ_CACHE_SECONDS = 10;

    private const int POLLING_FRESH_SECONDS = 120;

    public function __construct(
        protected MusicProviderFactory $providers,
        protected PlaybackControl $control,
        protected PlaybackStatus $status,
        protected StartPlayback $startPlayback,
        protected ContextPlaylist $context,
        protected UpcomingSongAugmentService $augment,
        protected FlaggedRequests $flagged,
        protected PartyTimezone $timezones,
    ) {}

    /**
     * @return array<string, Closure(): mixed>
     */
    public function props(Party $party, User $viewer): array
    {
        $party->loadMissing('user');

        return [
            'party' => fn (): array => [
                'code' => $party->code,
                'name' => $party->name,
                'active' => (bool) $party->active,
                'weighted' => (bool) $party->weighted,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($viewer),
            ],
            'host' => fn (): array => ['name' => $party->user->nickname],
            'can' => fn (): array => [
                'control' => $viewer->can('update', $party),
                'moderate' => $party->canBeManagedBy($viewer),
                'player' => $viewer->can('player', $party),
            ],
            'timezone' => fn (): string => $this->timezones->for($party),
            'playerState' => $party->getState(...),
            'control' => fn (): array => $this->control($party),
            'upNext' => fn (): array => $this->upNext($party, $viewer),
            'upcoming' => fn (): array => $this->upcoming($party, $viewer),
            'attention' => fn (): array => $this->attention($party),
            'links' => fn (): array => $this->links($party, $viewer),
            'hasFallbackPlaylist' => fn (): bool => $party->backup_playlist_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function control(Party $party): array
    {
        $needsRelink = $this->providers->partyNeedsRelink($party);
        $state = $needsRelink ? null : $this->playback($party);
        $recalled = $state ?? $this->status->recall($party);
        $playingFrom = $this->playingFrom($party, $recalled);
        $playing = $state?->isPlaying === true;

        return [
            'playingFrom' => $playingFrom,
            'nowPlaying' => $this->nowPlaying($party),
            'health' => $this->health($party, $recalled, $needsRelink),
            'start' => $this->start($party, $state, $playingFrom['kind'], $playing, $needsRelink),
        ];
    }

    public function playback(Party $party): ?PlaybackState
    {
        if ($this->providers->partyNeedsRelink($party)) {
            return null;
        }

        try {
            return Cache::remember(PartyDashboardCache::playbackKey($party), self::READ_CACHE_SECONDS, fn (): ?PlaybackState => $this->providers->forParty($party, Purpose::Control)->currentPlayback());
        } catch (ProviderBackoffException|ProviderRelinkRequiredException) {
            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return list<Device>
     */
    public function devices(Party $party): array
    {
        try {
            return Cache::remember(PartyDashboardCache::devicesKey($party), self::READ_CACHE_SECONDS, fn (): array => $this->control->devices($party));
        } catch (ProviderBackoffException|ProviderRelinkRequiredException) {
            return [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Fetches the devices live, bypassing and refreshing the cache.
     *
     * @return list<array<string, mixed>>
     */
    public function refreshDevices(Party $party): array
    {
        $devices = $this->control->devices($party);
        Cache::put(PartyDashboardCache::devicesKey($party), $devices, self::READ_CACHE_SECONDS);

        return array_map(fn (Device $device): array => $this->devicePresentation($party, $device), $devices);
    }

    /**
     * @return array{id: string|null, name: string, type: string, isActive: bool, isRestricted: bool, label: string, icon: string}
     */
    public function devicePresentation(Party $party, Device $device): array
    {
        $isEncore = $device->id !== null && $device->id === $party->recent_device_id && $party->playback_source === PlaybackSource::Encore;
        $base = $isEncore ? 'Encore' : ($device->type !== '' ? $device->type : 'Device');
        $label = match (true) {
            $device->isRestricted => "Can't be controlled remotely",
            $device->isActive => "{$base} · active now",
            default => $base,
        };

        return [
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'isActive' => $device->isActive,
            'isRestricted' => $device->isRestricted,
            'label' => $label,
            'icon' => match ($device->type) {
                'Speaker' => 'ti-device-speaker',
                'Computer' => 'ti-device-laptop',
                'Smartphone' => 'ti-device-mobile',
                'TV', 'CastVideo' => 'ti-device-tv',
                default => 'ti-device-desktop',
            },
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upNext(Party $party, User $viewer): array
    {
        return $this->requests($this->upNextRequests($party), $viewer);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function upcoming(Party $party, User $viewer): array
    {
        $skip = $this->upNextRequests($party)->whereNull('queued_at')->count();
        $items = $this->unsent($party)->skip($skip)->take(self::UPCOMING_SIZE)->get();

        return [
            'items' => $this->requests($items, $viewer, sent: false),
            'total' => $this->unsent($party)->count() + ($this->sentRequest($party) === null ? 0 : 1),
        ];
    }

    /**
     * @return array{issues: list<array<string, mixed>>, flagged: list<array<string, mixed>>, flaggedTotal: int}
     */
    public function attention(Party $party): array
    {
        $flagged = $this->flagged->listFor($party);

        return [
            'issues' => $this->issues($party),
            'flagged' => array_slice($flagged, 0, self::FLAGGED_SIZE),
            'flaggedTotal' => count($flagged),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function links(Party $party, User $viewer): array
    {
        $settings = route('parties.edit', $party->code);

        return [
            'queue' => route('parties.show', $party->code),
            'songs' => route('parties.songs.index', $party->code),
            'flagged' => route('parties.flagged.index', $party->code),
            'settings' => $settings,
            'members' => route('parties.users.index', $party->code),
            'moderation' => route('parties.moderation.index', $party->code),
            'mods' => route('parties.mods.edit', $party->code),
            'tv' => route('parties.tv', $party->code),
            'webPlayer' => $viewer->can('player', $party) ? route('parties.player', $party->code) : null,
            'encoreRelay' => $settings.'#encore-relay',
            'join' => route('parties.show', $party->code),
            'fallbackSettings' => $settings,
        ];
    }

    /**
     * @return array{kind: string, label: string|null}
     */
    protected function playingFrom(Party $party, ?PlaybackState $state): array
    {
        if ($state === null || $state->trackId === null) {
            return ['kind' => 'nothing', 'label' => null];
        }

        $provider = $this->providers->forParty($party, Purpose::Control);
        if ($state->contextUri === null) {
            return ['kind' => $state->isPlaying ? 'unknown' : 'nothing', 'label' => null];
        }
        if ($this->context->isPartyContext($party, $provider, $state->contextUri)) {
            return ['kind' => 'party', 'label' => null];
        }

        try {
            $name = $provider->contextName($state->contextUri);
        } catch (Throwable $e) {
            report($e);
            $name = null;
        }
        $isCollection = $name !== null && preg_match('/^spotify:user:[^:]+:collection$/D', $state->contextUri) === 1;

        return ['kind' => 'outside', 'label' => $isCollection ? "{$party->user->nickname}'s {$name}" : $name];
    }

    /**
     * @return array{isRequest: bool, requestedBy: string|null}
     */
    protected function nowPlaying(Party $party): array
    {
        $current = $party->song_id === null ? null : $party->current();
        $request = $current !== null && $current->song_id === $party->song_id ? $current->upcoming : null;

        return ['isRequest' => $request !== null, 'requestedBy' => $request?->user?->nickname];
    }

    /**
     * @return array<string, mixed>
     */
    protected function health(Party $party, ?PlaybackState $state, bool $needsRelink): array
    {
        $source = $party->playback_source;
        $fresh = $source !== null && $party->playback_received_at !== null && $party->playback_received_at->greaterThan(
            now()->subSeconds($source->isPushed() ? Party::PUSHED_PLAYBACK_FRESH_SECONDS : self::POLLING_FRESH_SECONDS)
        );

        return [
            'player' => [
                'source' => match ($source) {
                    PlaybackSource::Polling => 'polling',
                    PlaybackSource::Webhook => 'webhook',
                    PlaybackSource::WebPlayer => 'webplayer',
                    PlaybackSource::Encore => 'encore',
                    null => null,
                },
                'label' => match ($source) {
                    PlaybackSource::Polling => 'Polling',
                    PlaybackSource::Webhook => 'Webhook',
                    PlaybackSource::WebPlayer => 'Web Player',
                    PlaybackSource::Encore => 'Encore',
                    null => 'None',
                },
                'fresh' => $fresh,
            ],
            'device' => ['id' => $state->deviceId ?? $party->recent_device_id, 'name' => $state->deviceName ?? $party->device_name],
            'sampledAt' => $party->playback_sampled_at?->toIso8601String(),
            'link' => $needsRelink ? 'relink_required' : 'connected',
            'polling' => ! $party->poll ? 'off' : ($party->hasFreshPushedPlayback() ? 'backup' : 'on'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function start(Party $party, ?PlaybackState $state, string $playingFromKind, bool $playing, bool $needsRelink): array
    {
        $empty = ['target' => null, 'devices' => [], 'selectedDeviceId' => null];

        if ($needsRelink) {
            return ['state' => 'relink_required'] + $empty;
        }
        if (Cache::get(StartPlayback::premiumFlagKey($party)) === true) {
            return ['state' => 'premium_required'] + $empty;
        }
        if ($playingFromKind === 'party' && $playing) {
            return ['state' => 'hidden'] + $empty;
        }

        $devices = $this->devices($party);
        $usable = array_values(array_filter($devices, fn (Device $device): bool => ! $device->isRestricted && $device->id !== null));
        $target = $this->targetFor($party, $state);
        $kind = match (true) {
            $usable === [] => 'no_device',
            $playing => 'outside',
            $target?->kind === StartTargetKind::Resume => 'resume',
            default => 'idle',
        };

        return [
            'state' => $kind,
            'target' => $target === null ? null : $this->targetPresentation($party, $target),
            'devices' => array_map(fn (Device $device): array => $this->devicePresentation($party, $device), $devices),
            'selectedDeviceId' => $this->selectedDeviceId($party, $usable),
        ];
    }

    protected function targetFor(Party $party, ?PlaybackState $state): ?StartTarget
    {
        try {
            return $this->startPlayback->target($party, $state);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function targetPresentation(Party $party, StartTarget $target): array
    {
        $song = $target->request->song
            ?? ($party->song?->provider_track_id === $target->track->id ? $party->song : Song::fromTrack($target->track));

        return [
            'kind' => $target->kind->value,
            'track' => $song->toApi(),
            'positionMs' => $target->positionMs,
            'requestedBy' => $target->request?->user?->nickname,
        ];
    }

    /**
     * @param  list<Device>  $usable
     */
    protected function selectedDeviceId(Party $party, array $usable): ?string
    {
        foreach ($usable as $device) {
            if ($device->isActive) {
                return $device->id;
            }
        }
        foreach ([$party->device_id, $party->recent_device_id] as $preferred) {
            foreach ($usable as $device) {
                if ($preferred !== null && $device->id === $preferred) {
                    return $device->id;
                }
            }
        }

        return $usable[0]->id ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function issues(Party $party): array
    {
        $host = $party->user->nickname;
        $issues = [];

        if ($this->providers->partyNeedsRelink($party)) {
            $issues[] = ['code' => 'relink_required', 'fact' => 'Spotify link expired', 'detail' => "Ask {$host} to re-link", 'action' => null];
        }
        if (! $party->active) {
            $issues[] = ['code' => 'party_inactive', 'fact' => 'Party inactive', 'detail' => 'Nothing is sent to Spotify', 'action' => ['label' => 'Activate', 'kind' => 'activate']];
        }
        if ($party->playback_source?->isPushed() === true && ! $party->hasFreshPushedPlayback()) {
            $issues[] = ['code' => 'player_stale', 'fact' => 'Player not reporting', 'detail' => 'Polling is checking Spotify', 'action' => null];
        }
        if (Cache::get(StartPlayback::premiumFlagKey($party)) === true) {
            $issues[] = ['code' => 'premium_required', 'fact' => 'Spotify Premium required', 'detail' => "Ask {$host} to upgrade", 'action' => null];
        }

        return $issues;
    }

    /**
     * @return HasMany<UpcomingSong, Party>
     */
    protected function unsent(Party $party): HasMany
    {
        return $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNull('queued_at')
            ->whereNull('flagged_at')
            ->orderByRaw('scheduled_for is null')
            ->orderBy('scheduled_for')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    protected function sentRequest(Party $party): ?UpcomingSong
    {
        return $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNotNull('queued_at')
            ->doesntHave('played')
            ->orderByDesc('queued_at')
            ->first();
    }

    /**
     * @return Collection<int, UpcomingSong>
     */
    protected function upNextRequests(Party $party): Collection
    {
        $sent = $this->sentRequest($party);
        $items = $this->unsent($party)->take(self::UP_NEXT_SIZE - ($sent === null ? 0 : 1))->get();

        return new Collection($sent === null ? $items->all() : [$sent, ...$items->all()]);
    }

    /**
     * @param  Collection<int, UpcomingSong>  $requests
     * @return list<array<string, mixed>>
     */
    protected function requests(Collection $requests, User $viewer, ?bool $sent = null): array
    {
        $augmented = $this->augment->augmentCollection($requests, $viewer);

        return array_values($requests->map(function (UpcomingSong $request) use ($augmented, $sent): array {
            $resource = new UpcomingSongResource($request);
            $resource->augment($augmented[$request->id] ?? null);

            return $resource->toArray(request()) + ['sent' => $sent ?? $request->queued_at !== null];
        })->all());
    }
}
