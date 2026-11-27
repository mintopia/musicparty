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
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

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

        // Sorting
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

        $members = $query->paginate($perPage)->appends($params);

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
            'roles' => PartyMemberRole::all()
                ->sortBy(fn (PartyMemberRole $role): int|false => array_search($role->code, ['owner', 'moderator', 'vip', 'user', 'banned'], true))
                ->map(fn (PartyMemberRole $role): array => ['code' => $role->code, 'name' => $role->name, 'count' => (int) ($roleCounts[$role->id] ?? 0)])
                ->values(),
            'filters' => $params,
            'memberTotal' => (int) $roleCounts->sum(),
            'members' => $members,
        ]);
    }

    public function show(Request $request, Party $party, PartyMember $user): Response
    {
        $query = $user->user->votes()->whereHas('upcomingSong', function ($query) use ($party) {
            $query->wherePartyId($party->id);
        })->with(['upcomingSong', 'upcomingSong.song', 'upcomingSong.song.album', 'upcomingSong.song.artists']);

        $params = [
            'order' => 'created_at',
            'order_direction' => 'desc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        if ($request->input('name')) {
            $params['name'] = $request->input('name');
            $query = $query->whereHas('upcomingSong.song', function ($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['name']}%");
            });
        }
        if ($request->input('type')) {
            $params['type'] = $request->input('type');
            if ($request->input('type') === 'upvote') {
                $query = $query->where('value', '>', 0);
            } else {
                $query = $query->where('value', '<', 0);
            }
        }

        $votes = $query->orderBy($params['order'], $params['order_direction'])->paginate($perPage)->appends($params);

        $votes->through(fn (Vote $vote): array => [
            'id' => $vote->id,
            'songName' => $vote->upcomingSong->song->name,
            'albumName' => $vote->upcomingSong->song->album->name,
            'imageUrl' => $vote->upcomingSong->song->album->image_url,
            'artists' => $vote->upcomingSong->song->artists->pluck('name')->values()->all(),
            'url' => route('parties.songs.show', [$party->code, $vote->upcomingSong->id]),
            'value' => $vote->value,
            'createdAt' => $this->when($vote->created_at),
        ]);

        return Inertia::render('Parties/Members/Show', [
            'party' => $this->partyProps($party, $request->user()?->can('update', $party) ?? false),
            'playerState' => $party->getState(),
            'member' => $this->memberProps($user),
            'votes' => $votes,
            'filters' => $params,
            'types' => [
                ['code' => 'downvote', 'name' => 'Downvote'],
                ['code' => 'upvote', 'name' => 'Upvote'],
            ],
        ]);
    }

    public function edit(Request $request, Party $party, PartyMember $user): Response
    {
        return Inertia::render('Parties/Members/Edit', [
            'party' => $this->partyProps($party, true),
            'playerState' => $party->getState(),
            'member' => $this->memberProps($user),
            'isSelf' => $request->user()?->id === $user->user_id,
            'roles' => PartyMemberRole::all()->map(fn (PartyMemberRole $role): array => ['code' => $role->code, 'name' => $role->name])->values(),
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
