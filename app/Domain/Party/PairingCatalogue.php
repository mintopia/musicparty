<?php

namespace App\Domain\Party;

use App\Domain\Admin\PairingToggles;
use App\Domain\Identity\ProviderCredentials;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\PlayerFactory;

readonly class PairingCatalogue
{
    private const array LOCAL_ONLY_PROVIDERS = ['fake'];

    /**
     * @return list<array{id: string, label: string}>
     */
    public function providers(): array
    {
        $providers = [];
        foreach ($this->entries('music_providers') as $id => $entry) {
            if ($this->isProviderAvailable($id)) {
                $providers[] = ['id' => $id, 'label' => $entry['label']];
            }
        }

        return $providers;
    }

    /**
     * @return list<array{kind: string, label: string, compatibleProviders: list<string>, requiresControlLink: bool}>
     */
    public function players(): array
    {
        $availableProviders = array_column($this->providers(), 'id');
        $players = [];
        foreach ($this->entries('players') as $kind => $entry) {
            if (! app(PairingToggles::class)->playerEnabled($kind)) {
                continue;
            }

            $player = $this->player($kind);
            $compatible = array_values(array_intersect($player->compatibleProviders(), $availableProviders));
            if ($compatible === []) {
                continue;
            }

            $players[] = [
                'kind' => $kind,
                'label' => $entry['label'],
                'compatibleProviders' => $compatible,
                'requiresControlLink' => $player->requiresHostAccount(),
            ];
        }

        return $players;
    }

    public function isProviderAvailable(string $id): bool
    {
        $entry = $this->entries('music_providers')[$id] ?? null;
        if ($entry === null || ! app(PairingToggles::class)->providerEnabled($id) || ! $this->isPermittedHere($id)) {
            return false;
        }

        return $this->isConfigured($entry);
    }

    public function isPlayerAvailable(string $kind, string $providerId): bool
    {
        if (! isset($this->entries('players')[$kind]) || ! app(PairingToggles::class)->playerEnabled($kind)) {
            return false;
        }

        return $this->isProviderAvailable($providerId)
            && in_array($providerId, $this->player($kind)->compatibleProviders(), true);
    }

    /**
     * @return list<array{id: string, label: string, enabled: bool, configured: bool, requires: list<string>, localOnly: bool}>
     */
    public function registeredProviders(): array
    {
        $providers = [];
        foreach ($this->entries('music_providers') as $id => $entry) {
            if (! $this->isPermittedHere($id)) {
                continue;
            }

            $providers[] = [
                'id' => $id,
                'label' => $entry['label'],
                'enabled' => app(PairingToggles::class)->providerEnabled($id),
                'configured' => $this->isConfigured($entry),
                'requires' => $entry['requires'] ?? [],
                'localOnly' => in_array($id, self::LOCAL_ONLY_PROVIDERS, true),
            ];
        }

        return $providers;
    }

    /**
     * @return list<array{kind: string, label: string, enabled: bool, configured: bool, requires: list<string>, compatibleProviders: list<string>}>
     */
    public function registeredPlayers(): array
    {
        $players = [];
        foreach ($this->entries('players') as $kind => $entry) {
            $players[] = [
                'kind' => $kind,
                'label' => $entry['label'],
                'enabled' => app(PairingToggles::class)->playerEnabled($kind),
                'configured' => true,
                'requires' => [],
                'compatibleProviders' => $this->player($kind)->compatibleProviders(),
            ];
        }

        return $players;
    }

    public function provider(string $id): MusicProvider
    {
        /** @var MusicProvider */
        return app($this->entries('music_providers')[$id]['class']);
    }

    public function trackUrl(?string $providerId, ?string $providerTrackId): ?string
    {
        if ($providerId === null || ! isset($this->entries('music_providers')[$providerId])) {
            return null;
        }

        return $this->provider($providerId)->trackUrl($providerTrackId);
    }

    public function playlistUrl(?string $providerId, ?string $providerPlaylistId): ?string
    {
        if ($providerId === null || ! isset($this->entries('music_providers')[$providerId])) {
            return null;
        }

        return $this->provider($providerId)->playlistUrl($providerPlaylistId);
    }

    public function player(string $kind): Player
    {
        return app(PlayerFactory::class)->make($kind);
    }

    private function isPermittedHere(string $id): bool
    {
        return ! in_array($id, self::LOCAL_ONLY_PROVIDERS, true) || app()->environment('local', 'testing');
    }

    /**
     * @param  array{label: string, class: string, requires?: list<string>}  $entry
     */
    private function isConfigured(array $entry): bool
    {
        $credentials = app(ProviderCredentials::class);
        foreach ($entry['requires'] ?? [] as $code) {
            if (! $credentials->isConfigured($code)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{label: string, class: string, requires?: list<string>}>
     */
    private function entries(string $key): array
    {
        /** @var array<string, array{label: string, class: string, requires?: list<string>}> */
        return config("musicparty.{$key}", []);
    }
}
