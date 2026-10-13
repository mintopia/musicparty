<?php

namespace App\Services\Requests;

use App\Enums\Purpose;
use App\Events\FlaggedRequestAdded;
use App\Http\Resources\TrackAnnotationResource;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\ModExtensions;
use App\Services\Music\MusicProviderFactory;
use App\Services\QueueSelector;
use Carbon\CarbonInterface;

class FlaggedRequests
{
    public function __construct(protected MusicProviderFactory $providers, protected QueueSelector $queue, protected ModExtensions $mods) {}

    public function flag(Party $party, Song $song, User $user, string $reason, ?CarbonInterface $scheduledFor = null): UpcomingSong
    {
        $attributes = ['flagged_at' => now(), 'flag_reason' => $reason];
        if ($scheduledFor !== null) {
            $attributes += ['scheduled_for' => $scheduledFor, 'not_before' => $scheduledFor];
        }
        $request = $this->queue->addRequest($party, $song, $user, $attributes);
        FlaggedRequestAdded::dispatch($party, $this->item($request->load(['party', 'user', 'song.album', 'song.artists'])));

        return $request;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFor(Party $party): array
    {
        return array_values($party->upcoming()
            ->whereNull('queued_at')
            ->whereNotNull('flagged_at')
            ->with(['user', 'song.album', 'song.artists'])
            ->orderBy('flagged_at')
            ->orderBy('id')
            ->get()
            ->map(fn (UpcomingSong $request): array => $this->item($request))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function item(UpcomingSong $request): array
    {
        $party = $request->party;
        $provider = $this->providers->forParty($party, Purpose::Search);
        $track = $request->song->toTrack();
        $annotation = new TrackAnnotation(
            track: $track,
            upcoming: false,
            upcomingId: $request->id,
            score: 0,
            vote: null,
            user: $request->user->nickname ?? null,
            allowed: true,
            reason: $request->flag_reason,
            externalUrl: $provider->trackUrl($track->id),
            providerName: $provider->displayName(),
            flagged: true,
            decoration: $this->mods->decorationFor($party, $request->song, $request)?->toArray(),
        );

        return [
            'id' => $request->id,
            'reason' => $request->flag_reason,
            'flagged_at' => $request->flagged_at?->toIso8601String(),
            'requester' => ['id' => $request->user_id, 'name' => $request->user->nickname ?? 'Unknown'],
            'track' => new TrackAnnotationResource($annotation)->resolve(),
        ];
    }
}
