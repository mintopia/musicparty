<?php

namespace App\Domain\Party;

use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Playback\Contracts\Player;

readonly class PairingCatalogue
{
    /**
     * @return list<array{id: string, label: string}>
     */
    public function providers(): array
    {
        $providers = [];
        foreach ($this->entries('music_providers') as $id => $entry) {
            $providers[] = ['id' => $id, 'label' => $entry['label']];
        }

        return $providers;
    }

    /**
     * @return list<array{kind: string, label: string, compatibleProviders: list<string>}>
     */
    public function players(): array
    {
        $players = [];
        foreach ($this->entries('players') as $kind => $entry) {
            $players[] = [
                'kind' => $kind,
                'label' => $entry['label'],
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

    public function player(string $kind): Player
    {
        /** @var Player */
        return app($this->entries('players')[$kind]['class']);
    }

    /**
     * @return array<string, array{label: string, class: string}>
     */
    private function entries(string $key): array
    {
        /** @var array<string, array{label: string, class: string}> */
        return config("musicparty.{$key}", []);
    }
}
