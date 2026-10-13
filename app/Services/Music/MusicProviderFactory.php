<?php

namespace App\Services\Music;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use Closure;
use InvalidArgumentException;

class MusicProviderFactory
{
    public const DEFAULT_PROVIDER_ID = 'spotify';

    /**
     * @param  array<string, Closure(User, Purpose): (MusicProvider&ProviderPlayback)>  $registry
     */
    public function __construct(protected array $registry = []) {}

    /**
     * @param  Closure(User, Purpose): (MusicProvider&ProviderPlayback)  $resolver
     */
    public function register(string $id, Closure $resolver): void
    {
        $this->registry[$id] = $resolver;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->registry);
    }

    public function forParty(Party $party, Purpose $purpose): MusicProvider&ProviderPlayback
    {
        $id = $this->providerIdFor($party);
        $resolver = $this->registry[$id] ?? throw new InvalidArgumentException("Unknown music provider [{$id}]");

        return $resolver($party->user, $purpose);
    }

    protected function providerIdFor(Party $party): string
    {
        $id = $party->getAttribute('provider_id');

        return is_string($id) && $id !== '' ? $id : self::DEFAULT_PROVIDER_ID;
    }
}
