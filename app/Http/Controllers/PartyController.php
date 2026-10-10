<?php

namespace App\Http\Controllers;

use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\BanMember;
use App\Domain\Party\Actions\ChangeMemberRole;
use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\EndParty;
use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\ListPartyMembers;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\ReopenParty;
use App\Domain\Party\Actions\UnbanMember;
use App\Domain\Party\Actions\UpdatePartySettings;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Actions\ControlPlayback;
use App\Domain\Playback\Exceptions\PlaybackControlRefusedException;
use App\Domain\Queue\Actions\ApproveRequest;
use App\Domain\Queue\Actions\ListPendingRequests;
use App\Domain\Queue\Actions\ListPlayHistory;
use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\Actions\RateNowPlaying;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Actions\RejectRequest;
use App\Domain\Queue\Actions\RemoveRequest;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SearchPartyProvider;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use App\Http\Requests\Api\V1\ChangeMemberRoleRequest;
use App\Http\Requests\CastVoteRequest;
use App\Http\Requests\ControlPlaybackRequest;
use App\Http\Requests\JoinPartyRequest;
use App\Http\Requests\ListPlayHistoryRequest;
use App\Http\Requests\RateNowPlayingRequest;
use App\Http\Requests\RatePlayRequest;
use App\Http\Requests\RejectRequestRequest;
use App\Http\Requests\RequestTrackRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\V1\PartyLogEntryResource;
use App\Http\Resources\V1\PartyMemberResource;
use App\Http\Resources\V1\PlayResource;
use App\Http\Resources\V1\QueueEntryResource;
use App\Http\Resources\V1\SearchHitResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\PlayRating;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function create(Request $request, PairingCatalogue $catalogue): Response
    {
        $this->authorize('create', Party::class);

        return Inertia::render('Party/Create', [
            'providers' => $catalogue->providers(),
            'players' => $catalogue->players(),
        ]);
    }

    public function store(StorePartyRequest $request, CreateParty $createParty): RedirectResponse
    {
        $party = $createParty(
            $this->currentUser($request),
            $request->string('name')->trim()->toString(),
            $request->string('music_provider')->toString(),
            $request->string('player_kind')->toString(),
        );

        return redirect()->route('parties.show', ['party' => $party->code])->with('successMessage', 'Party created');
    }

    public function join(JoinPartyRequest $request, JoinParty $joinParty): RedirectResponse
    {
        $party = Party::findByCode($request->string('code')->toString());

        if ($party === null) {
            return back()->withErrors(['code' => 'No party found with that code.']);
        }

        $joinParty($this->currentUser($request), $party);

        return redirect()->route('parties.show', ['party' => $party->code]);
    }

    public function show(
        ListPlayHistoryRequest $request,
        JoinParty $joinParty,
        ListQueue $listQueue,
        ListPlayHistory $listHistory,
        PartyQueueSnapshot $snapshot,
        SearchPartyProvider $search,
        EnabledMods $enabledMods,
        Party $party,
        string $section = 'queue',
    ): Response {
        $member = $joinParty($this->currentUser($request), $party);
        $playback = $snapshot->build($party);
        $query = trim($request->string('q')->toString());
        $results = null;
        $searchError = null;

        if ($query !== '') {
            try {
                $results = SearchHitResource::collection($search($party, $query))->resolve($request);
            } catch (RequestRefusedException $exception) {
                $results = [];
                $searchError = $exception->getMessage();
            }
        }

        return Inertia::render('Party/Show', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'state' => $party->state->value,
                'musicProvider' => $party->music_provider,
                'playerKind' => $party->player_kind,
                'downvotes' => (bool) $party->downvotes,
            ],
            'membership' => [
                'role' => $member->role->value,
                'banned' => $member->banned,
            ],
            'section' => $section,
            'canManage' => $party->canBeManagedBy($this->currentUser($request)),
            'canManageBlocklist' => $this->currentUser($request)->can('moderate', $party),
            'readOnly' => $party->state === PartyState::Ended || $member->banned,
            'nowPlaying' => $playback['now_playing'],
            'myRating' => $this->myRating($playback['now_playing'], $member),
            'ratablePlay' => $this->ratablePlay($request, $party, $member),
            'upNext' => $playback['up_next'],
            'queue' => QueueEntryResource::collection($listQueue($party, $member))->resolve($request),
            'history' => $section === 'history' ? PlayResource::collection($listHistory($party, $member, $request->filters())) : null,
            'filters' => $request->filters(),
            'search_query' => $query,
            'results' => $results,
            'search_error' => $searchError,
            'enabled_mods' => $enabledMods->idsFor($party),
        ]);
    }

    public function settings(EnabledMods $enabledMods, Party $party): Response
    {
        $this->authorize('update', $party);

        return Inertia::render('Party/Settings', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'settings' => [
                'allow_requests' => (bool) $party->allow_requests,
                'max_requests' => $party->max_requests,
                'min_song_length' => $party->min_song_length,
                'max_song_length' => $party->max_song_length,
                'explicit' => (bool) $party->explicit,
                'hold_requests' => (bool) $party->hold_requests,
                'no_repeat_interval' => $party->no_repeat_interval,
            ],
            'enabled_mods' => $enabledMods->idsFor($party),
        ]);
    }

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): RedirectResponse
    {
        $this->authorize('update', $party);

        $settings = $request->safe()->only(['name', 'fallback_playlist_id', 'allow_requests', 'max_requests', 'explicit', 'min_song_length', 'max_song_length', 'no_repeat_interval', 'hold_requests', 'downvotes', 'downvotes_per_hour', 'selection_mode']);
        $result = $updateSettings($this->currentUser($request), $party, $settings);

        $redirect = back()->with('successMessage', 'Settings saved');
        $warning = $result['warning'];

        return $warning === null ? $redirect : $redirect->with('warningMessage', $warning->message());
    }

    public function live(Request $request, GoLiveParty $goLive, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $goLive($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party is live');
    }

    public function pause(Request $request, PauseParty $pauseParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $pauseParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party paused');
    }

    public function end(Request $request, EndParty $endParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $endParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party ended');
    }

    public function reopen(Request $request, ReopenParty $reopenParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $reopenParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party reopened');
    }

    public function log(Request $request, ListPartyLog $listLog, Party $party): Response
    {
        $this->authorize('viewLog', $party);

        return Inertia::render('Party/Log', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'entries' => PartyLogEntryResource::collection($listLog($party)),
        ]);
    }

    public function members(Request $request, ListPartyMembers $listMembers, Party $party): Response
    {
        $this->authorize('viewMembers', $party);
        $user = $this->currentUser($request);

        $members = PartyMemberResource::collection($listMembers($party, $user->can('moderate', $party)))
            ->resolve($request);

        return Inertia::render('Party/Members', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'members' => array_map(fn (array $member): array => [
                'id' => $member['id'],
                'nickname' => $member['nickname'],
                'avatar' => $member['avatar'],
                'role' => $member['role'],
                'banned' => $member['banned'],
                'isYou' => $member['is_you'],
            ], $members),
            'abilities' => [
                'canChangeRoles' => $user->can('manageRoles', $party),
                'canBan' => $user->can('moderate', $party),
            ],
        ]);
    }

    public function changeMemberRole(ChangeMemberRoleRequest $request, ChangeMemberRole $changeRole, Party $party, PartyMember $member): RedirectResponse
    {
        $this->authorize('manageRoles', $party);
        abort_unless($member->party_id === $party->id, 404);

        $changed = $changeRole($this->currentUser($request), $party, $member, $request->role());

        return back()->with('successMessage', "{$changed->holder()->nickname} is now {$changed->role->value}");
    }

    public function banMember(Request $request, BanMember $banMember, Party $party, PartyMember $member): RedirectResponse
    {
        $this->authorize('moderate', $party);
        abort_unless($member->party_id === $party->id, 404);

        $banned = $banMember($this->currentUser($request), $party, $member);

        return back()->with('successMessage', "{$banned->holder()->nickname} was banned");
    }

    public function unbanMember(Request $request, UnbanMember $unbanMember, Party $party, PartyMember $member): RedirectResponse
    {
        $this->authorize('moderate', $party);
        abort_unless($member->party_id === $party->id, 404);

        $unbanned = $unbanMember($this->currentUser($request), $party, $member);

        return back()->with('successMessage', "{$unbanned->holder()->nickname} was unbanned");
    }

    public function storeRequest(RequestTrackRequest $request, RequestTrack $requestTrack, Party $party): RedirectResponse
    {
        $member = $party->memberFor($this->currentUser($request));

        try {
            $outcome = $requestTrack(
                $party,
                $member ?? throw RequestRefusedException::notAMember(),
                $request->string('provider_track_id')->toString(),
            );
        } catch (RequestRefusedException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        $message = match (true) {
            $outcome->created => 'Track requested',
            $outcome->voteAdded => 'Already in the queue, your vote was added',
            default => 'Already in the queue and you have already voted for it',
        };

        return back()->with('success', $message)->with('successMessage', $message);
    }

    public function pendingRequests(Request $request, ListPendingRequests $listPending, Party $party): Response
    {
        try {
            $pending = $listPending($this->currentUser($request), $party);
        } catch (RequestRefusedException $exception) {
            abort($exception->status(), $exception->getMessage());
        }

        return Inertia::render('Party/Pending', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'canModerate' => $this->currentUser($request)->can('moderate', $party),
            'requests' => QueueEntryResource::collection($pending)->resolve($request),
        ]);
    }

    public function approveRequest(Request $request, ApproveRequest $approve, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        return $this->decideRequest(fn () => $approve($this->currentUser($request), $party, $trackRequest), 'Request approved');
    }

    public function rejectRequest(RejectRequestRequest $request, RejectRequest $reject, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        return $this->decideRequest(fn () => $reject($this->currentUser($request), $party, $trackRequest, $request->reason()), 'Request rejected');
    }

    public function destroyRequest(Request $request, RemoveRequest $remove, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        return $this->decideRequest(fn () => $remove($this->currentUser($request), $party, $trackRequest), 'Request removed');
    }

    /**
     * @param  callable(): TrackRequest  $decision
     */
    private function decideRequest(callable $decision, string $message): RedirectResponse
    {
        try {
            $decision();
        } catch (RequestRefusedException $exception) {
            abort_if($exception->status() === RequestRefusedException::NOT_ALLOWED, 403, $exception->getMessage());

            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        return back()->with('successMessage', $message);
    }

    public function storeVote(CastVoteRequest $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        return $this->applyVote($request, $vote, $party, $trackRequest, $request->direction());
    }

    public function destroyVote(Request $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        return $this->applyVote($request, $vote, $party, $trackRequest, null);
    }

    public function storeRating(RatePlayRequest $request, RatePlay $ratePlay, Party $party, Play $play): RedirectResponse
    {
        return $this->applyRating($request, $ratePlay, $party, $play, $request->direction());
    }

    public function destroyRating(Request $request, RatePlay $ratePlay, Party $party, Play $play): RedirectResponse
    {
        return $this->applyRating($request, $ratePlay, $party, $play, null);
    }

    private function applyRating(Request $request, RatePlay $ratePlay, Party $party, Play $play, ?VoteDirection $direction): RedirectResponse
    {
        abort_unless($play->party_id === $party->id, 404);

        try {
            $ratePlay($party->memberFor($this->currentUser($request)) ?? throw RequestRefusedException::notAMember(), $play, $direction);
        } catch (RequestRefusedException $exception) {
            return back()->withErrors(['rating' => $exception->getMessage()]);
        }

        return back();
    }

    private function applyVote(Request $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest, ?VoteDirection $direction): RedirectResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        try {
            $vote($party, $party->memberFor($this->currentUser($request)) ?? throw RequestRefusedException::notAMember(), $trackRequest, $direction);
        } catch (RequestRefusedException $exception) {
            return back()->withErrors(['vote' => $exception->getMessage()]);
        }

        return back();
    }

    public function control(ControlPlaybackRequest $request, ControlPlayback $controlPlayback, Party $party): RedirectResponse
    {
        try {
            $controlPlayback($party, $request->control(), $request->value());
        } catch (PlaybackControlRefusedException $exception) {
            return back()->withErrors(['playback' => $exception->getMessage()]);
        }

        return back();
    }

    public function storeNowPlayingRating(RateNowPlayingRequest $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        return $this->applyNowPlayingRating($request, $rate, $party, $trackRequest, $request->direction());
    }

    public function destroyNowPlayingRating(Request $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest): RedirectResponse
    {
        return $this->applyNowPlayingRating($request, $rate, $party, $trackRequest, null);
    }

    private function applyNowPlayingRating(Request $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest, ?VoteDirection $direction): RedirectResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        try {
            $rate($party, $party->memberFor($this->currentUser($request)) ?? throw RequestRefusedException::notAMember(), $trackRequest, $direction);
        } catch (RequestRefusedException $exception) {
            return back()->withErrors(['rating' => $exception->getMessage()]);
        }

        return back();
    }

    /**
     * @param  array<string, mixed>|null  $nowPlaying
     */
    private function myRating(?array $nowPlaying, PartyMember $member): int
    {
        if ($nowPlaying === null) {
            return 0;
        }

        return (int) PlayRating::query()
            ->where('track_request_id', $nowPlaying['id'])
            ->where('party_member_id', $member->id)
            ->value('value');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ratablePlay(Request $request, Party $party, PartyMember $member): ?array
    {
        $play = Play::query()
            ->where('party_id', $party->id)
            ->whereHas('request', fn ($query) => $query->where('status', RequestStatus::Playing))
            ->withHistoryRelations()
            ->withRatingSummary($member)
            ->orderByDesc('played_at')
            ->orderByDesc('id')
            ->first();

        return $play === null ? null : new PlayResource($play)->resolve($request);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
