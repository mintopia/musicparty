<?php

namespace App\Domain\Playback\Players;

use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Broadcast\PlayerCommandEvent;
use App\Domain\Playback\Contracts\BindsToParty;
use App\Domain\Playback\Contracts\HandlesPlayerFrames;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\EnqueueBackoff;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\PlayerHealth;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SoloistPlayer implements BindsToParty, HandlesPlayerFrames, Player
{
    public const string KIND = 'soloist';

    private const int STATE_TTL_SECONDS = 86400;

    private const int TRACKED_LIMIT = 50;

    private const array USER_UNOWNED_SOURCES = ['autoplay', 'context'];

    private ?string $partyCode = null;

    public function __construct(private readonly RecordPartyLogEntry $record) {}

    public function forParty(Party $party): self
    {
        $this->partyCode = $party->code;

        return $this;
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function compatibleProviders(): array
    {
        return [SpotifyMusicProvider::ID];
    }

    public function feedMode(): FeedMode
    {
        return FeedMode::JustInTime;
    }

    public function requiresHostAccount(): bool
    {
        return false;
    }

    public function supports(Control $control): bool
    {
        return true;
    }

    public function state(): PlaybackState
    {
        $stored = $this->get('state');

        if ($this->health() === PlayerHealth::Disconnected || ! is_array($stored) || ! isset($stored['status'])) {
            return PlaybackState::stopped();
        }

        $track = isset($stored['track']) ? new TrackReference(is_string($stored['provider'] ?? null) ? $stored['provider'] : (string) $this->party()?->music_provider, (string) $stored['track']) : null;

        return new PlaybackState(
            PlaybackStatus::from((string) $stored['status']),
            $track,
            (int) ($stored['position_ms'] ?? 0),
            isset($stored['updated_at']) ? CarbonImmutable::parse((string) $stored['updated_at']) : null,
            isset($stored['duration_ms']) ? (int) $stored['duration_ms'] : null,
            $this->list('upcoming'),
        );
    }

    public function health(): PlayerHealth
    {
        $stored = $this->get('health');

        return is_string($stored) ? (PlayerHealth::tryFrom($stored) ?? PlayerHealth::Disconnected) : PlayerHealth::Disconnected;
    }

    public function markConnected(): void
    {
        $this->put('last_frame', CarbonImmutable::now()->toIso8601String());
        $this->transition(PlayerHealth::Connected, 'player.connected');
        $this->dispatchFrame(SoloistCommands::getState());
    }

    public function markDisconnected(string $reason): void
    {
        $this->transition(PlayerHealth::Disconnected, 'player.disconnected', ['reason' => $reason]);
        $this->forget('last_frame');
        $this->put('state', ['status' => PlaybackStatus::Stopped->value, 'updated_at' => CarbonImmutable::now()->toIso8601String()]);
    }

    public function checkHealth(): void
    {
        $health = $this->health();
        $last = $this->get('last_frame');

        if ($health === PlayerHealth::Disconnected || ! is_string($last) || $this->state()->status !== PlaybackStatus::Playing) {
            return;
        }

        $silence = CarbonImmutable::parse($last)->diffInSeconds(CarbonImmutable::now(), true);

        if ($silence > (int) config('musicparty.soloist.disconnect_after_seconds')) {
            $this->markDisconnected('no_frames');

            return;
        }

        if ($silence > (int) config('musicparty.soloist.stale_after_seconds') && $health === PlayerHealth::Connected) {
            $this->transition(PlayerHealth::Stale, 'player.stale', ['silent_seconds' => (int) $silence]);
            $this->dispatchFrame(SoloistCommands::getState());
        }
    }

    public function handleFrame(array $frame): bool
    {
        $type = $frame['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return false;
        }

        $this->noteFrame();

        return match ($type) {
            'playback_state' => $this->onPlaybackState($frame),
            'playback_changed' => $this->onPlaybackChanged($frame),
            'position_sync' => $this->onPositionSync($frame),
            'volume_changed' => $this->onVolumeChanged($frame),
            'track_changed' => $this->onTrackChanged($frame),
            'queue_changed' => $this->onQueueChanged($frame),
            'error' => $this->onError($frame),
            default => true,
        };
    }

    public function enqueue(string $providerId, string $providerTrackId): void
    {
        $this->assertConnected();

        $alreadyQueued = in_array($providerTrackId, $this->list('sent'), true)
            && (in_array($providerTrackId, $this->list('upcoming'), true) || $this->state()->currentTrack?->providerTrackId === $providerTrackId);

        if ($alreadyQueued) {
            return;
        }

        $this->dispatchFrame(SoloistCommands::addToQueue($providerTrackId));
        $this->put('sent', array_slice([...array_diff($this->list('sent'), [$providerTrackId]), $providerTrackId], -self::TRACKED_LIMIT));
    }

    public function play(): void
    {
        $this->send(SoloistCommands::play());
    }

    public function pause(): void
    {
        $this->send(SoloistCommands::pause());
    }

    public function skip(): void
    {
        $this->send(SoloistCommands::next());
    }

    public function seek(int $positionMs): void
    {
        $this->send(SoloistCommands::seek($positionMs));
    }

    public function volume(int $level): void
    {
        $this->send(SoloistCommands::volume($level));
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onPlaybackState(array $frame): bool
    {
        $status = $this->status($frame['status'] ?? null);
        $item = $frame['item'] ?? null;
        $parsed = $item === null ? null : $this->item($item);

        if ($status === null || ($item !== null && $parsed === null) || ! $this->validPosition($frame)) {
            return false;
        }

        if (isset($frame['volume']) && is_int($frame['volume'])) {
            $this->put('volume', $frame['volume']);
        }

        $this->apply($status, $parsed, $this->positionMs($frame));

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onPlaybackChanged(array $frame): bool
    {
        $status = $this->status($frame['status'] ?? null);

        if ($status === null) {
            return false;
        }

        $previous = $this->state();
        $this->apply($status, $status === PlaybackStatus::Stopped ? null : $this->currentItem($previous), $previous->positionMs);

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onPositionSync(array $frame): bool
    {
        if (! $this->validPosition($frame) || ! isset($frame['position'])) {
            return false;
        }

        $this->merge(['position_ms' => $this->positionMs($frame), 'updated_at' => CarbonImmutable::now()->toIso8601String()]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onVolumeChanged(array $frame): bool
    {
        if (! is_int($frame['volume'] ?? null)) {
            return false;
        }

        $this->put('volume', $frame['volume']);

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onTrackChanged(array $frame): bool
    {
        $item = $this->item($frame['item'] ?? null);

        if ($item === null) {
            return false;
        }

        $this->apply(PlaybackStatus::Playing, $item, 0);

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onQueueChanged(array $frame): bool
    {
        $upcoming = $frame['upcoming'] ?? null;

        if (! is_array($upcoming)) {
            return false;
        }

        $tracks = [];
        $unowned = [];

        foreach ($upcoming as $entry) {
            $parsed = is_array($entry) ? $this->item($entry['item'] ?? null) : null;

            if ($parsed === null) {
                return false;
            }

            $tracks[] = $parsed['track'];
            $source = $entry['source'] ?? null;

            if (is_string($source) && in_array($source, self::USER_UNOWNED_SOURCES, true)) {
                $unowned[] = ['key' => 'uid:'.(is_scalar($entry['uid'] ?? null) ? $entry['uid'] : $parsed['track']), 'source' => $source, 'track' => $parsed['track']];
            }
        }

        $this->put('upcoming', $tracks);

        foreach ($unowned as $detected) {
            if ($this->markHandled($detected['key'])) {
                $this->log('player.autoplay_detected', $detected['track'], ['source' => $detected['source']]);
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function onError(array $frame): bool
    {
        $message = $frame['message'] ?? ($frame['error'] ?? null);

        $this->log('player.error', null, ['message' => is_scalar($message) ? mb_substr((string) $message, 0, 200) : null]);

        return true;
    }

    /**
     * @param  array{track: string, duration_ms: ?int}|null  $item
     */
    private function apply(PlaybackStatus $status, ?array $item, int $positionMs): void
    {
        $previous = $this->state();
        $track = $status === PlaybackStatus::Stopped || $item === null ? null : $item['track'];
        $changed = $track !== null && $track !== $previous->currentTrack?->providerTrackId;
        $guarded = $changed && ! $this->isOurs($track) && $this->markHandled("track:{$track}");

        if ($guarded) {
            $status = PlaybackStatus::Paused;
        }

        $this->put('state', [
            'status' => $status->value,
            'provider' => $this->party()?->music_provider,
            'track' => $track,
            'position_ms' => $positionMs,
            'duration_ms' => $item['duration_ms'] ?? null,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        if ($guarded) {
            $this->dispatchFrame(SoloistCommands::pause());
            $this->log('player.autoplay_detected', $track, ['source' => 'track_change']);
        }

        if ($changed) {
            $this->forgetSent($previous->currentTrack?->providerTrackId);
            $party = $this->party();

            if ($party !== null) {
                app(PlaybackCoordinator::class)->trackChanged($party, $track);
            }

            return;
        }

        if ($track === null && $previous->currentTrack !== null) {
            $this->forgetSent($previous->currentTrack->providerTrackId);
            $party = $this->party();

            if ($party !== null) {
                app(PlaybackCoordinator::class)->playbackEnded($party);
            }
        }
    }

    /**
     * @return array{track: string, duration_ms: ?int}|null
     */
    private function item(mixed $item): ?array
    {
        if (! is_array($item) || ! is_string($item['uri'] ?? null) || ! preg_match('/^spotify:track:([A-Za-z0-9]+)$/', $item['uri'], $matches)) {
            return null;
        }

        $duration = $item['decorations']['playback']['duration_ms'] ?? null;

        return ['track' => $matches[1], 'duration_ms' => is_int($duration) ? $duration : null];
    }

    /**
     * @return array{track: string, duration_ms: ?int}|null
     */
    private function currentItem(PlaybackState $state): ?array
    {
        return $state->currentTrack === null ? null : ['track' => $state->currentTrack->providerTrackId, 'duration_ms' => $state->durationMs];
    }

    private function status(mixed $status): ?PlaybackStatus
    {
        return match ($status) {
            'playing', 'buffering' => PlaybackStatus::Playing,
            'paused' => PlaybackStatus::Paused,
            'idle' => PlaybackStatus::Stopped,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function validPosition(array $frame): bool
    {
        $position = $frame['position'] ?? null;

        return $position === null || (is_array($position) && is_int($position['position_ms'] ?? null) && $position['position_ms'] >= 0);
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function positionMs(array $frame): int
    {
        $position = $frame['position'] ?? null;

        return is_array($position) ? (int) $position['position_ms'] : 0;
    }

    private function noteFrame(): void
    {
        $this->put('last_frame', CarbonImmutable::now()->toIso8601String());

        if ($this->health() !== PlayerHealth::Connected) {
            $reconnected = $this->health() === PlayerHealth::Disconnected;
            $this->transition(PlayerHealth::Connected, 'player.connected');

            if ($reconnected) {
                $this->dispatchFrame(SoloistCommands::getState());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function transition(PlayerHealth $to, string $action, array $details = []): void
    {
        $stored = $this->get('health');

        if ($stored === $to->value) {
            return;
        }

        $this->put('health', $to->value);
        $this->log($action, null, $details === [] ? null : $details);

        if ($to === PlayerHealth::Connected && ($party = $this->party()) !== null) {
            app(EnqueueBackoff::class)->clearForParty($party);
        }
    }

    private function assertConnected(): void
    {
        if ($this->health() === PlayerHealth::Disconnected) {
            throw new PlayerDisconnectedException('The Soloist is disconnected.');
        }
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function send(array $frame): void
    {
        $this->assertConnected();
        $this->dispatchFrame($frame);
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function dispatchFrame(array $frame): void
    {
        if ($this->partyCode !== null) {
            PlayerCommandEvent::dispatch($this->partyCode, $frame);
        }
    }

    private function isOurs(string $providerTrackId): bool
    {
        $party = $this->party();

        return in_array($providerTrackId, $this->list('sent'), true)
            || ($party !== null && TrackRequest::query()
                ->where('party_id', $party->id)
                ->whereIn('status', [RequestStatus::UpNext, RequestStatus::Playing])
                ->where('provider_track_id', $providerTrackId)
                ->exists());
    }

    private function markHandled(string $key): bool
    {
        $handled = $this->list('handled');

        if (in_array($key, $handled, true)) {
            return false;
        }

        $this->put('handled', array_slice([...$handled, $key], -self::TRACKED_LIMIT));

        return true;
    }

    private function forgetSent(?string $providerTrackId): void
    {
        if ($providerTrackId !== null) {
            $this->put('sent', array_values(array_diff($this->list('sent'), [$providerTrackId])));
        }
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    private function log(string $action, ?string $subject, ?array $details): void
    {
        $party = $this->party();

        if ($party !== null) {
            ($this->record)($party, $action, subject: $subject, details: $details, systemActor: 'player');
        }
    }

    private function party(): ?Party
    {
        return $this->partyCode === null ? null : Party::findByCode($this->partyCode);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function merge(array $changes): void
    {
        $stored = $this->get('state');
        $this->put('state', [...(is_array($stored) ? $stored : ['status' => PlaybackStatus::Stopped->value]), ...$changes]);
    }

    /**
     * @return list<string>
     */
    private function list(string $name): array
    {
        $stored = $this->get($name);

        return is_array($stored) ? array_values(array_map(strval(...), $stored)) : [];
    }

    private function get(string $name): mixed
    {
        return $this->partyCode === null ? null : Cache::get($this->key($name));
    }

    private function put(string $name, mixed $value): void
    {
        Cache::put($this->key($name), $value, self::STATE_TTL_SECONDS);
    }

    private function forget(string $name): void
    {
        Cache::forget($this->key($name));
    }

    private function key(string $name): string
    {
        return "playback.soloist.{$this->partyCode}.{$name}";
    }
}
