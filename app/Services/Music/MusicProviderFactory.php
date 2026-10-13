<?php

namespace App\Services\Music;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\Setting;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use Closure;
use InvalidArgumentException;

class MusicProviderFactory
{
    public const DEFAULT_PROVIDER_ID = 'spotify';

    /**
     * @param  array<string, Closure(User, Purpose, ?string): (MusicProvider&ProviderPlayback)>  $registry
     */
    public function __construct(protected array $registry = []) {}

    /**
     * @param  Closure(User, Purpose, ?string): (MusicProvider&ProviderPlayback)  $resolver
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

    public function isEnabled(string $id): bool
    {
        $raw = Setting::query()->where('code', "provider-{$id}-enabled")->first()?->getRawOriginal('value');

        // SettingValue reads a stored false back as true, so compare the serialized value.
        return $raw !== 'b:0;';
    }

    /**
     * Ids a Party may be created with: registered, admin-enabled, and Fake only in `local`.
     *
     * @return list<string>
     */
    public function selectableIds(): array
    {
        return array_values(array_filter(
            $this->ids(),
            fn (string $id): bool => $this->isEnabled($id) && ($id !== 'fake' || app()->environment('local')),
        ));
    }

    public function forParty(Party $party, Purpose $purpose): MusicProvider&ProviderPlayback
    {
        $id = $this->providerIdFor($party);
        $resolver = $this->registry[$id] ?? throw new InvalidArgumentException("Unknown music provider [{$id}]");

        return $resolver($party->user, $purpose, $party->country);
    }

    public function forUser(string $id, User $user, Purpose $purpose): MusicProvider&ProviderPlayback
    {
        $resolver = $this->registry[$id] ?? throw new InvalidArgumentException("Unknown music provider [{$id}]");

        return $resolver($user, $purpose, null);
    }

    public function partyNeedsRelink(Party $party): bool
    {
        return $this->forParty($party, Purpose::Control)->needsRelink();
    }

    protected function providerIdFor(Party $party): string
    {
        $id = $party->getAttribute('provider_id');

        return is_string($id) && $id !== '' ? $id : self::DEFAULT_PROVIDER_ID;
    }
}
