<?php

namespace App\Domain\Stats\Actions;

use App\Domain\Party\PartyState;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\TrackRequest;

readonly class BuildPartyExport
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Party $party): array
    {
        if ($party->state !== PartyState::Ended) {
            throw RequestRefusedException::partyNotEnded();
        }

        $requests = TrackRequest::query()
            ->where('party_id', $party->id)
            ->withCount([
                'votes as upvotes' => fn ($votes) => $votes->where('value', '>', 0),
                'votes as downvotes' => fn ($votes) => $votes->where('value', '<', 0),
            ])
            ->orderBy('id')
            ->get();

        $plays = Play::query()
            ->where('party_id', $party->id)
            ->withCount([
                'memberRatings as likes' => fn ($ratings) => $ratings->where('value', '>', 0),
                'memberRatings as dislikes' => fn ($ratings) => $ratings->where('value', '<', 0),
            ])
            ->orderBy('played_at')
            ->orderBy('id')
            ->get();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'state' => $party->state->value,
                'music_provider' => $party->music_provider,
                'selection_mode' => $party->selection_mode->value,
                'created_at' => $party->created_at?->toIso8601String(),
            ],
            'members' => $this->members($party),
            'requests' => $requests->map(fn (TrackRequest $request): array => [
                'id' => $request->id,
                'provider_track_id' => $request->provider_track_id,
                'title' => $request->title,
                'artists' => $request->artists,
                'album' => $request->album,
                'duration_ms' => $request->duration_ms,
                'explicit' => (bool) $request->explicit,
                'status' => $request->status->value,
                'requested_by' => $request->party_member_id,
                'requested_at' => $request->created_at?->toIso8601String(),
                'rejection_reason' => $request->rejection_reason,
            ])->all(),
            'plays' => $plays->map(fn (Play $play): array => [
                'id' => $play->id,
                'request_id' => $play->track_request_id,
                'provider_track_id' => $play->provider_track_id,
                'title' => $play->title,
                'artists' => $play->artists,
                'album' => $play->album,
                'duration_ms' => $play->duration_ms,
                'explicit' => (bool) $play->explicit,
                'requested_by' => $play->party_member_id,
                'selection_mode' => $play->selection_mode,
                'selection_score' => $play->selection_score,
                'played_at' => $play->played_at->toIso8601String(),
            ])->all(),
            'votes' => $requests->filter(fn (TrackRequest $request): bool => $request->upvotes + $request->downvotes > 0)
                ->map(fn (TrackRequest $request): array => [
                    'request_id' => $request->id,
                    'upvotes' => (int) $request->upvotes,
                    'downvotes' => (int) $request->downvotes,
                    'score' => (int) $request->upvotes - (int) $request->downvotes,
                ])->values()->all(),
            'ratings' => $plays->filter(fn (Play $play): bool => $play->likes + $play->dislikes > 0)
                ->map(fn (Play $play): array => [
                    'play_id' => $play->id,
                    'likes' => (int) $play->likes,
                    'dislikes' => (int) $play->dislikes,
                    'score' => (int) $play->likes - (int) $play->dislikes,
                ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, nickname: string, role: string}>
     */
    private function members(Party $party): array
    {
        return PartyMember::query()
            ->where('party_id', $party->id)
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (PartyMember $member): array => [
                'id' => $member->id,
                'nickname' => $member->holder()->nickname,
                'role' => $member->role->value,
            ])
            ->values()
            ->all();
    }
}
