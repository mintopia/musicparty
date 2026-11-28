<?php

namespace App\Services\Requests\Rules;

use App\Models\UpcomingSong;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestRule;
use Illuminate\Support\Facades\DB;

final class SimilarTrackRule implements RequestRule
{
    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        $waiting = $context->remember('similar-keys', fn (): array => $this->waitingKeys($context));

        return isset($waiting[$this->key($track->name, array_map(fn (ArtistRef $artist): string => $artist->name, $track->artists))])
            ? RequestDecision::reject('A similar song is already in the queue')
            : RequestDecision::allow();
    }

    /**
     * @return array<string, true>
     */
    protected function waitingKeys(RequestContext $context): array
    {
        $names = [];
        $artists = [];
        foreach ($context->tracks as $track) {
            $names[] = mb_strtolower($track->name);
            foreach ($track->artists as $artist) {
                $artists[] = mb_strtolower($artist->name);
            }
        }

        $waiting = $context->party->upcoming()->whereNull('queued_at')->whereNull('flagged_at')->whereHas('song', function ($query) use ($names, $artists) {
            $query->whereIn(DB::raw('lower(name)'), $names);
            $query->whereHas('artists', fn ($query) => $query->whereIn(DB::raw('lower(name)'), $artists));
        })->with(['song', 'song.artists'])->get();

        $keys = [];
        /** @var UpcomingSong $upcoming */
        foreach ($waiting as $upcoming) {
            $keys[$this->key($upcoming->song->name, array_values($upcoming->song->artists->pluck('name')->all()))] = true;
        }

        return $keys;
    }

    /**
     * @param  list<string>  $artistNames
     */
    protected function key(string $name, array $artistNames): string
    {
        return mb_strtolower("{$name}:".implode('', array_map(fn (string $artist): string => ":{$artist}", $artistNames)));
    }
}
