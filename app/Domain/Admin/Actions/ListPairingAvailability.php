<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\ProviderCredentials;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;

class ListPairingAvailability
{
    public function __construct(
        private readonly PairingCatalogue $catalogue,
        private readonly ProviderCredentials $credentials,
    ) {}

    /**
     * @return array{
     *     musicProviders: list<array{id: string, label: string, enabled: bool, configured: bool, missing: list<array{code: string, editUrl: string}>, parties: int}>,
     *     players: list<array{kind: string, label: string, enabled: bool, compatibleProviders: list<string>, parties: int}>
     * }
     */
    public function handle(): array
    {
        $providerCounts = $this->nonEndedCounts('music_provider');
        $playerCounts = $this->nonEndedCounts('player_kind');

        $registeredProviders = $this->catalogue->registeredProviders();
        $labels = array_column($registeredProviders, 'label', 'id');

        $providers = [];
        foreach ($registeredProviders as $provider) {
            $providers[] = [
                'id' => $provider['id'],
                'label' => $provider['label'],
                'enabled' => $provider['enabled'],
                'configured' => $provider['configured'],
                'missing' => $this->missing($provider['requires']),
                'parties' => $providerCounts[$provider['id']] ?? 0,
            ];
        }

        $players = [];
        foreach ($this->catalogue->registeredPlayers() as $player) {
            $players[] = [
                'kind' => $player['kind'],
                'label' => $player['label'],
                'enabled' => $player['enabled'],
                'compatibleProviders' => array_values(array_filter(array_map(
                    fn (string $id): ?string => $labels[$id] ?? null,
                    $player['compatibleProviders'],
                ))),
                'parties' => $playerCounts[$player['kind']] ?? 0,
            ];
        }

        return ['musicProviders' => $providers, 'players' => $players];
    }

    /**
     * @param  list<string>  $requires
     * @return list<array{code: string, editUrl: string}>
     */
    private function missing(array $requires): array
    {
        $missing = [];
        foreach ($requires as $code) {
            if (! $this->credentials->isConfigured($code)) {
                $missing[] = ['code' => $code, 'editUrl' => route('admin.providers.edit', $code)];
            }
        }

        return $missing;
    }

    /**
     * @return array<string, int>
     */
    private function nonEndedCounts(string $column): array
    {
        /** @var array<string, int> */
        return Party::query()
            ->where('state', '!=', PartyState::Ended->value)
            ->whereNotNull($column)
            ->groupBy($column)
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->pluck('aggregate', $column)
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
