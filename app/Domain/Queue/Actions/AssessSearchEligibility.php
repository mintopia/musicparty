<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\CheapRequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\EnabledMods;
use App\Domain\Mod\RuleOutcome;
use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

readonly class AssessSearchEligibility
{
    public function __construct(private Blocklist $blocklist, private EnabledMods $mods) {}

    /**
     * Assesses a whole page of Tracks with a fixed number of queries; AI Request Review and other non-cheap rules are never run.
     *
     * @param  list<TrackData>  $tracks
     * @return array<string, ?string> refusal reason per provider track id, null when the Track can be requested
     */
    public function __invoke(Party $party, PartyMember $member, array $tracks): array
    {
        $partyBlock = $this->partyBlock($party, $member);

        if ($partyBlock !== null) {
            return array_fill_keys(array_map(fn (TrackData $track): string => $track->providerTrackId, $tracks), $partyBlock->getMessage());
        }

        $limitBlock = $this->limitBlock($party, $member);
        $entries = $this->blocklist->enabledEntries($party);
        $played = $party->no_repeat_interval
            ? $this->requestsWithStatus($party, [RequestStatus::Playing, RequestStatus::Played])->where('updated_at', '>=', now()->subSeconds($party->no_repeat_interval))->sortByDesc('updated_at')
            : collect();
        $upNext = $this->requestsWithStatus($party, [RequestStatus::UpNext]);
        $cheapRules = $this->cheapRules($party);

        $reasons = [];

        foreach ($tracks as $track) {
            $reasons[$track->providerTrackId] = ($limitBlock ?? $this->trackBlock($party, $track, $entries, $played, $upNext) ?? $this->modBlock($cheapRules, $member, $track))?->getMessage();
        }

        return $reasons;
    }

    private function partyBlock(Party $party, PartyMember $member): ?RequestRefusedException
    {
        return match (true) {
            $member->banned => RequestRefusedException::banned(),
            $party->state === PartyState::Ended => RequestRefusedException::partyNotLive(),
            $party->state === PartyState::Paused && $party->freeze_when_paused => RequestRefusedException::requestsFrozen(),
            ! $party->allow_requests => RequestRefusedException::requestsDisabled(),
            default => null,
        };
    }

    private function limitBlock(Party $party, PartyMember $member): ?RequestRefusedException
    {
        if ($party->max_requests === null || $member->role->isExemptFromRequestLimit()) {
            return null;
        }

        $active = TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('party_member_id', $member->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued, RequestStatus::UpNext])
            ->count();

        return $active >= $party->max_requests ? RequestRefusedException::requestLimitReached($party->max_requests) : null;
    }

    /**
     * @param  EloquentCollection<int, BlocklistEntry>  $entries
     * @param  Collection<int, TrackRequest>  $played
     * @param  Collection<int, TrackRequest>  $upNext
     */
    private function trackBlock(Party $party, TrackData $track, Collection $entries, Collection $played, Collection $upNext): ?RequestRefusedException
    {
        $seconds = $track->durationMs / 1000;

        if ($party->min_song_length && $seconds < $party->min_song_length) {
            return RequestRefusedException::trackTooShort($party->min_song_length);
        }

        if ($party->max_song_length && $seconds > $party->max_song_length) {
            return RequestRefusedException::trackTooLong($party->max_song_length);
        }

        if (! $party->explicit && $track->explicit) {
            return RequestRefusedException::explicitNotAllowed();
        }

        if ($this->blocklist->firstMatchIn($entries, $track) !== null) {
            return RequestRefusedException::blocklisted();
        }

        $recent = $played->first(fn (TrackRequest $request): bool => $this->sameTrack($request, $track));

        if ($recent !== null) {
            return RequestRefusedException::playedRecently($recent->updated_at);
        }

        return $upNext->contains(fn (TrackRequest $request): bool => $this->sameTrack($request, $track)) ? RequestRefusedException::alreadyUpNext() : null;
    }

    /**
     * @param  list<array{rule: CheapRequestRule, context: ModContext, mod: string}>  $rules
     */
    private function modBlock(array $rules, PartyMember $member, TrackData $track): ?RequestRefusedException
    {
        foreach ($rules as $entry) {
            try {
                $verdict = $entry['rule']->judge($entry['context'], $member, $track);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($verdict->outcome === RuleOutcome::Reject) {
                return RequestRefusedException::rejectedByMod($entry['mod'], $verdict->reason ?? 'No reason given.');
            }
        }

        return null;
    }

    /**
     * @return list<array{rule: CheapRequestRule, context: ModContext, mod: string}>
     */
    private function cheapRules(Party $party): array
    {
        $rules = [];

        foreach ($this->mods->for($party) as $enabled) {
            foreach ($enabled->mod->requestRules() as $rule) {
                if ($rule instanceof CheapRequestRule) {
                    $rules[] = ['rule' => $rule, 'context' => $enabled->context, 'mod' => $enabled->mod->name()];
                }
            }
        }

        return $rules;
    }

    /**
     * @param  list<RequestStatus>  $statuses
     * @return Collection<int, TrackRequest>
     */
    private function requestsWithStatus(Party $party, array $statuses): Collection
    {
        return TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereIn('status', $statuses)
            ->get(['id', 'provider_track_id', 'isrc', 'updated_at']);
    }

    private function sameTrack(TrackRequest $request, TrackData $track): bool
    {
        return $request->provider_track_id === $track->providerTrackId || ($track->isrc !== null && $request->isrc === $track->isrc);
    }
}
