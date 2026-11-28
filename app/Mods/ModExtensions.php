<?php

namespace App\Mods;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\Contracts\DecoratesTracks;
use App\Mods\Contracts\Mod;
use App\Services\ModDataStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModExtensions
{
    public function __construct(protected ModRegistry $registry, protected PartyMods $partyMods) {}

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return list<T>
     */
    public function for(Party $party, string $contract): array
    {
        $extensions = [];
        foreach ($this->registry->all() as $code => $mod) {
            if ($mod instanceof $contract && $this->partyMods->isEnabled($party, $code)) {
                $extensions[] = $mod;
            }
        }

        return $extensions;
    }

    /**
     * Fetches the data every track-decorating mod needs for a page of Requests in one query per mod.
     *
     * @param  Collection<int, UpcomingSong>  $requests
     */
    public function preloadDecorations(Party $party, Collection $requests): void
    {
        foreach ($this->for($party, DecoratesTracks::class) as $mod) {
            if ($mod instanceof Mod) {
                app(ModDataStore::class)->preload($requests, $mod->code());
            }
        }
    }

    public function decorationFor(Party $party, Song $song, ?UpcomingSong $request = null): ?TrackDecoration
    {
        /** @var array<string, string> $merged */
        $merged = [];
        foreach ($this->for($party, DecoratesTracks::class) as $mod) {
            try {
                $decoration = $mod->decorate($party, $song, $request);
            } catch (Throwable $exception) {
                Log::warning("{$party}: Mod ".$mod::class." failed to decorate a track: {$exception->getMessage()}");

                continue;
            }
            foreach ($decoration?->toArray() ?? [] as $field => $value) {
                $merged[$field] ??= $value;
            }
        }

        if ($merged === []) {
            return null;
        }

        return new TrackDecoration($merged['displayName'] ?? null, $merged['label'] ?? null, $merged['class'] ?? null, $merged['accent'] ?? null);
    }
}
