<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Actions\ChangePartyPlaybackDevice;
use App\Domain\Party\Actions\ChangePartyPlayerSelection;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Players\PollingPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class ChangePartyPlayer
{
    public function __construct(
        private AuthorisesHost $authorisesHost,
        private PairingCatalogue $catalogue,
        private PairPlayer $pairPlayer,
        private RecordPartyLogEntry $record,
        private PartyPlayers $players,
        private ChangePartyPlayerSelection $changeSelection,
        private ChangePartyPlaybackDevice $changeDevice,
    ) {}

    /**
     * @throws NotHostException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Party $party, string $playerKind, ?string $musicProvider = null, ?string $deviceId = null, ?string $deviceName = null, bool $deviceProvided = false): Party
    {
        ($this->authorisesHost)($actor, $party);

        $musicProvider ??= $party->music_provider;

        $keepsCurrentPairing = $playerKind === $party->player_kind && $musicProvider === $party->music_provider;

        if (! $keepsCurrentPairing) {
            $this->assertAvailable($playerKind, $musicProvider);
        }

        try {
            ($this->pairPlayer)($this->catalogue->player($playerKind), $this->catalogue->provider($musicProvider));
        } catch (IncompatibleProviderException $exception) {
            throw ValidationException::withMessages(['player_kind' => $exception->getMessage()]);
        }

        $from = ['player_kind' => $party->player_kind, 'music_provider' => $party->music_provider];
        $to = ['player_kind' => $playerKind, 'music_provider' => $musicProvider];

        $device = $this->resolveDevice($party, $playerKind, $deviceId, $deviceName, $deviceProvided);

        if ($device['id'] !== $party->playback_device_id || $device['name'] !== $party->playback_device_name) {
            $from['playback_device'] = $party->playback_device_id === null ? null : ['id' => $party->playback_device_id, 'name' => $party->playback_device_name];
            $to['playback_device'] = $device['id'] === null ? null : $device;
        }

        if ($from === $to) {
            return $party;
        }

        DB::transaction(function () use ($actor, $party, $from, $to, $device): void {
            ($this->changeSelection)($party, $to['player_kind'], $to['music_provider']);
            ($this->changeDevice)($party, $device['id'], $device['name']);

            ($this->record)($party, 'player.changed', $actor, null, ['from' => $from, 'to' => $to]);
        });

        $this->players->forget($party);
        PollPlayback::start($party);

        return $party;
    }

    /**
     * @throws ValidationException
     */
    private function assertAvailable(string $playerKind, string $musicProvider): void
    {
        $registeredKinds = array_column($this->catalogue->registeredPlayers(), 'kind');

        if (! in_array($playerKind, $registeredKinds, true)) {
            throw ValidationException::withMessages(['player_kind' => "Unknown player kind '{$playerKind}'."]);
        }

        if (! $this->catalogue->isProviderAvailable($musicProvider)) {
            throw ValidationException::withMessages(['music_provider' => "Unknown Music Provider '{$musicProvider}'."]);
        }

        $enabled = collect($this->catalogue->registeredPlayers())->firstWhere('kind', $playerKind)['enabled'] ?? false;

        if (! $enabled) {
            throw ValidationException::withMessages(['player_kind' => 'This player has been disabled by an admin.']);
        }
    }

    /**
     * @return array{id: string|null, name: string|null}
     */
    private function resolveDevice(Party $party, string $playerKind, ?string $deviceId, ?string $deviceName, bool $deviceProvided): array
    {
        if ($playerKind !== PollingPlayer::KIND) {
            return ['id' => null, 'name' => null];
        }

        if (! $deviceProvided) {
            return ['id' => $party->playback_device_id, 'name' => $party->playback_device_name];
        }

        return $deviceId === null ? ['id' => null, 'name' => null] : ['id' => $deviceId, 'name' => $deviceName];
    }
}
