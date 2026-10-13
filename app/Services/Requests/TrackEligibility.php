<?php

namespace App\Services\Requests;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Vote;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;

class TrackEligibility
{
    public function __construct(protected RequestRules $rules, protected MusicProviderFactory $providers) {}

    /**
     * @param  list<Track>  $tracks
     * @return list<TrackAnnotation>
     */
    public function annotate(Party $party, PartyMember $member, array $tracks): array
    {
        if ($tracks === []) {
            return [];
        }

        $provider = $this->providers->forParty($party, Purpose::Search);
        $waiting = $this->waitingByProviderId($party, $member, $tracks);
        $decisions = $this->rules->checkMany($party, $member, $tracks, RequestMode::Annotate);

        $annotations = [];
        foreach ($tracks as $track) {
            $entry = $waiting[$track->id] ?? null;
            $decision = $decisions[$track->id];
            $annotations[] = new TrackAnnotation(
                track: $track,
                upcoming: $entry !== null,
                upcomingId: $entry['id'] ?? null,
                score: $entry['score'] ?? 0,
                vote: $entry['vote'] ?? null,
                user: $entry['user'] ?? null,
                allowed: $decision->allowed,
                reason: $decision->reason,
                externalUrl: $provider->trackUrl($track->id),
                providerName: $provider->displayName(),
                flagged: $decision->isFlagged(),
            );
        }

        return $annotations;
    }

    /**
     * @param  list<Track>  $tracks
     * @return array<string, array{id: int, score: int, vote: int|null, user: string|null}>
     */
    protected function waitingByProviderId(Party $party, PartyMember $member, array $tracks): array
    {
        $ids = array_map(fn (Track $track): string => $track->id, $tracks);
        $upcomingSongs = $party->upcoming()->with(['song', 'user'])->whereNull('queued_at')
            ->whereHas('song', fn ($query) => $query->whereIn('provider_track_id', $ids))
            ->get();
        if ($upcomingSongs->isEmpty()) {
            return [];
        }

        $votes = Vote::whereUserId($member->user->id)->whereIn('upcoming_song_id', $upcomingSongs->pluck('id'))->pluck('value', 'upcoming_song_id');

        $waiting = [];
        foreach ($upcomingSongs as $upcoming) {
            $waiting[$upcoming->song->provider_track_id] = [
                'id' => $upcoming->id,
                'score' => $upcoming->score,
                'vote' => $votes[$upcoming->id] ?? null,
                'user' => $upcoming->user->nickname ?? null,
            ];
        }

        return $waiting;
    }
}
