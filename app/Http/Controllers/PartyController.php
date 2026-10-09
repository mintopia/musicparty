<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\EndParty;
use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\ReopenParty;
use App\Domain\Party\Actions\UpdatePartySettings;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\ListPlayHistory;
use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SearchPartyProvider;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\VoteDirection;
use App\Http\Requests\CastVoteRequest;
use App\Http\Requests\JoinPartyRequest;
use App\Http\Requests\ListPlayHistoryRequest;
use App\Http\Requests\RatePlayRequest;
use App\Http\Requests\RequestTrackRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\V1\PartyLogEntryResource;
use App\Http\Resources\V1\PlayResource;
use App\Http\Resources\V1\QueueEntryResource;
use App\Http\Resources\V1\SearchHitResource;
use App\Models\Party;
use App\Models\Play;
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
            'readOnly' => $party->state === PartyState::Ended || $member->banned,
            'nowPlaying' => $playback['now_playing'],
            'upNext' => $playback['up_next'],
            'queue' => QueueEntryResource::collection($listQueue($party, $member))->resolve($request),
            'history' => $section === 'history' ? PlayResource::collection($listHistory($party, $member, $request->filters())) : null,
            'filters' => $request->filters(),
            'search_query' => $query,
            'results' => $results,
            'search_error' => $searchError,
        ]);
    }

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): RedirectResponse
    {
        $this->authorize('update', $party);

        $settings = $request->safe()->only(['name', 'fallback_playlist_id', 'explicit', 'min_song_length', 'max_song_length', 'no_repeat_interval', 'downvotes', 'downvotes_per_hour']);
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

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
