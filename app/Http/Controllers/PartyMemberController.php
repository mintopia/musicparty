<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyMemberSearchRequest;
use App\Http\Requests\PartyMemberUpdateRequest;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Support\SearchTokens;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyMemberController extends Controller
{
    public function index(PartyMemberSearchRequest $request, Party $party): Response
    {
        $viewer = $request->user();
        $partyUpcoming = fn () => UpcomingSong::query()
            ->whereColumn('upcoming_songs.user_id', 'party_members.user_id')
            ->where('upcoming_songs.party_id', $party->id);
        $query = $party->members()->with(['user', 'role'])
            ->select('party_members.*')
            ->selectSub($partyUpcoming()->selectRaw('count(*)'), 'requests_count')
            ->selectSub($partyUpcoming()->whereNotNull('flagged_at')->selectRaw('count(*)'), 'flagged_count')
            ->selectSub(
                Vote::query()->selectRaw('count(*)')
                    ->whereColumn('votes.user_id', 'party_members.user_id')
                    ->whereIn('votes.upcoming_song_id', UpcomingSong::query()->select('id')->where('party_id', $party->id)),
                'votes_count',
            );
        $params = [
            'order' => 'id',
            'order_direction' => 'asc',
        ];
        $perPage = (int) $request->input('per_page', 20);

        $search = SearchTokens::parse((string) $request->input('q', ''), ['role']);
        if ($request->filled('nickname')) {
            $search['text'] = trim($search['text'].' '.$request->input('nickname'));
        }
        if ($request->filled('role')) {
            $search['tokens']['role'][] = (string) $request->input('role');
        }
        $params['q'] = SearchTokens::serialize($search);

        foreach (SearchTokens::words($search['text']) as $word) {
            $term = "%{$word}%";
            $query = $query->where(function ($query) use ($term) {
                $query->whereHas('user', fn ($user) => $user->where('nickname', 'LIKE', $term))
                    ->orWhereHas('role', fn ($role) => $role->where('name', 'LIKE', $term)->orWhere('code', 'LIKE', $term));
            });
        }
        foreach ($search['tokens']['role'] ?? [] as $value) {
            $query = $query->whereHas('role', fn ($role) => $role->where('code', $value)->orWhere('name', $value));
        }

        if ($request->input('order')) {
            $params['order'] = $request->input('order');
        }
        if ($request->input('order_direction')) {
            $params['order_direction'] = $request->input('order_direction');
        }

        switch ($params['order']) {
            case 'id':
            case 'created_at':
                $query = $query->orderBy($params['order'], $params['order_direction']);
                break;
            case 'role':
                $query = $query->orderBy('role_id', $params['order_direction']);
                break;
            case 'nickname':
                $query = $query->join('users', 'users.id', '=', 'party_members.user_id')
                    ->orderBy('users.nickname', $params['order_direction']);
                break;
        }

        $members = $query->orderBy('party_members.id')->paginate($perPage)->appends($params);

        $members->through(fn (PartyMember $member): array => [
            'id' => $member->id,
            'nickname' => $member->user->nickname,
            'role' => $member->role->name,
            'roleCode' => $member->role->code,
            'createdAt' => $this->when($member->created_at),
            'requests' => (int) $member->getAttribute('requests_count'),
            'votes' => (int) $member->getAttribute('votes_count'),
            'flagged' => (int) $member->getAttribute('flagged_count'),
            'isYou' => $member->user_id === $viewer?->id,
            'url' => route('parties.users.show', [$party->code, $member->id]),
            'editUrl' => route('parties.users.edit', [$party->code, $member->id]),
        ]);

        $roleCounts = $party->members()->selectRaw('role_id, count(*) as total')->groupBy('role_id')->pluck('total', 'role_id');

        return Inertia::render('Parties/Members/Index', [
            'party' => $this->partyProps($party, $request->user()?->can('update', $party) ?? false),
            'playerState' => $party->getState(),
            'roles' => $this->orderedRoles()
                ->map(fn (PartyMemberRole $role): array => ['code' => $role->code, 'name' => $role->name, 'count' => (int) ($roleCounts[$role->id] ?? 0)])
                ->values(),
            'filters' => $params + ($perPage !== 20 ? ['per_page' => (string) $perPage] : []),
            'memberTotal' => (int) $roleCounts->sum(),
            'members' => $members,
        ]);
    }

    public function show(Request $request, Party $party, PartyMember $user): Response
    {
        $canManage = $request->user()?->can('update', $party) ?? false;
        $relations = ['song.album', 'song.artists', 'played', 'aiReview'];
        $requestedSongs = UpcomingSong::query()
            ->where('party_id', $party->id)
            ->where('user_id', $user->user_id)
            ->with($relations)
            ->latest()
            ->latest('id')
            ->get();

        $requests = $requestedSongs->map(fn (UpcomingSong $song): array => [
            'id' => $song->id,
            ...$this->trackProps($party, $song),
            'status' => $song->played !== null ? 'played' : ($song->flagged_at !== null ? 'flagged' : 'waiting'),
            'score' => $song->score,
            'createdAt' => $this->when($song->created_at),
        ])->values();

        $flags = $requestedSongs->filter(fn (UpcomingSong $song): bool => $song->flagged_at !== null)
            ->map(fn (UpcomingSong $song): array => [
                'id' => $song->id,
                ...$this->trackProps($party, $song),
                'reason' => $this->flagReason($song),
                'reviewUrl' => $song->aiReview ? route('parties.ai-reviews.show', [$party->code, $song->aiReview->id]) : null,
                'outcome' => 'Waiting for approval',
            ])->values();

        $votes = $user->user->votes()
            ->whereHas('upcomingSong', fn ($query) => $query->where('party_id', $party->id))
            ->with(['upcomingSong.song.album', 'upcomingSong.song.artists'])
            ->latest()
            ->get()
            ->map(fn (Vote $vote): array => [
                'id' => $vote->id,
                ...$this->trackProps($party, $vote->upcomingSong),
                'value' => $vote->value,
                'createdAt' => $this->when($vote->created_at),
            ])->values();

        return Inertia::render('Parties/Members/Show', [
            'party' => $this->partyProps($party, $canManage),
            'playerState' => $party->getState(),
            'member' => [
                ...$this->memberProps($user),
                'lastRequestAt' => $this->when($requestedSongs->first()?->created_at),
                'requestCount' => $requests->count(),
                'voteCount' => $votes->count(),
                'flaggedCount' => $flags->count(),
                'canBan' => $canManage && $user->role->code !== 'owner',
            ],
            'requests' => $requests,
            'votes' => $votes,
            'flags' => $flags,
        ]);
    }

    public function edit(Request $request, Party $party, PartyMember $user): Response
    {
        return Inertia::render('Parties/Members/Edit', [
            'party' => $this->partyProps($party, true),
            'playerState' => $party->getState(),
            'member' => $this->memberProps($user),
            'isSelf' => $request->user()?->id === $user->user_id,
            'roles' => $this->orderedRoles()->map(fn (PartyMemberRole $role): array => ['code' => $role->code, 'name' => $role->name])->values(),
        ]);
    }

    public function update(PartyMemberUpdateRequest $request, Party $party, PartyMember $user): RedirectResponse
    {
        $role = PartyMemberRole::whereCode($request->input('role'))->first();
        $user->role()->associate($role);
        $user->save();

        return response()->redirectToRoute('parties.users.show', [$party->code, $user->id])->with('successMessage', 'The user has been updated');
    }

    /**
     * @return Collection<int, PartyMemberRole>
     */
    private function orderedRoles(): Collection
    {
        return PartyMemberRole::all()
            ->sortBy(fn (PartyMemberRole $role): int|false => array_search($role->code, ['owner', 'moderator', 'vip', 'user', 'banned'], true))
            ->values();
    }

    /**
     * @return array{songName: string, albumName: string, imageUrl: string, artists: list<string>, url: string}
     */
    private function trackProps(Party $party, UpcomingSong $song): array
    {
        return [
            'songName' => $song->song->name,
            'albumName' => $song->song->album->name,
            'imageUrl' => $song->song->album->image_url,
            'artists' => array_values($song->song->artists->pluck('name')->all()),
            'url' => route('parties.songs.show', [$party->code, $song->id]),
        ];
    }

    private function flagReason(UpcomingSong $song): string
    {
        $review = $song->aiReview;
        if ($review !== null && $review->criterion !== null && $review->criterion_confidence !== null) {
            return sprintf('AI review: %s, %d%% confident.', strtolower($review->criterion->label()), round($review->criterion_confidence * 100));
        }

        return $song->flag_reason ?? 'Flagged for review.';
    }

    /**
     * @return array{code: string, name: string, canManage: bool, canModerate: bool, ownerName: string}
     */
    private function partyProps(Party $party, bool $canManage): array
    {
        return ['code' => $party->code, 'name' => $party->name, 'canManage' => $canManage, 'canModerate' => true, 'ownerName' => $party->user->nickname];
    }

    /**
     * @return array{id: int, nickname: string, role: string, roleCode: string, createdAt: array{iso: string, human: string}|null}
     */
    private function memberProps(PartyMember $member): array
    {
        return [
            'id' => $member->id,
            'nickname' => $member->user->nickname,
            'role' => $member->role->name,
            'roleCode' => $member->role->code,
            'createdAt' => $this->when($member->created_at),
        ];
    }

    /**
     * @return array{iso: string, human: string}|null
     */
    private function when(?CarbonInterface $moment): ?array
    {
        return $moment ? ['iso' => $moment->format('Y-m-d H:i:s'), 'human' => $moment->diffForHumans()] : null;
    }
}
