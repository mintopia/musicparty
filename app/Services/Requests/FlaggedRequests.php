<?php

namespace App\Services\Requests;

use App\Enums\Purpose;
use App\Events\FlaggedRequestAdded;
use App\Http\Resources\TrackAnnotationResource;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\ModExtensions;
use App\Mods\PartyMods;
use App\Services\Music\MusicProviderFactory;
use App\Services\QueueSelector;
use Carbon\CarbonInterface;

class FlaggedRequests
{
    public function __construct(protected MusicProviderFactory $providers, protected QueueSelector $queue, protected ModExtensions $mods, protected PartyMods $partyMods) {}

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
        $reviewsAvailable = $this->partyMods->isEnabled($party, AiRequestReviewMod::CODE);

        return array_values($party->upcoming()
            ->whereNull('queued_at')
            ->whereNotNull('flagged_at')
            ->with(['user', 'song.album', 'song.artists', 'aiReview'])
            ->orderByDesc('flagged_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UpcomingSong $request): array => $this->item($request, $reviewsAvailable))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function item(UpcomingSong $request, ?bool $reviewsAvailable = null): array
    {
        $party = $request->party;
        $provider = $this->providers->forParty($party, Purpose::Search);
        $track = $request->song->toTrack();
        $review = $request->loadMissing('aiReview')->aiReview;
        $reviewsAvailable ??= $this->partyMods->isEnabled($party, AiRequestReviewMod::CODE);
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
            'rule_url' => $review === null || ! $reviewsAvailable ? route('parties.moderation.index', $party->code) : null,
            'ai_review' => $review !== null && $reviewsAvailable ? [
                'criterion' => $review->criterion?->label(),
                'confidence' => $review->confidence,
                'reason' => $review->reason,
            ] : null,
            'ai_review_url' => $review !== null && $reviewsAvailable ? route('parties.ai-reviews.show', [$party->code, $review]) : null,
            'track' => new TrackAnnotationResource($annotation)->resolve(),
        ];
    }
}
