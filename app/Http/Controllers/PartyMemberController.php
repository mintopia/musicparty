<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyMemberSearchRequest;
use App\Http\Requests\PartyMemberUpdateRequest;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Vote;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyMemberController extends Controller
{
    public function index(PartyMemberSearchRequest $request, Party $party): Response
    {
        $query = $party->members()->with(['user', 'role']);
        $params = [
            'order' => 'id',
            'order_direction' => 'asc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        if ($request->input('nickname')) {
            $params['nickname'] = $request->input('nickname');
            $query = $query->whereHas('user', function ($query) use ($params) {
                $query->where('nickname', 'LIKE', "%{$params['nickname']}%");
            });
        }

        if ($request->input('role')) {
            $params['role'] = $request->input('role');
            $query = $query->whereHas('role', function ($query) use ($params) {
                $query->whereCode($params['role']);
            });
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
                    ->select('party_members.*')
                    ->orderBy('users.nickname', $params['order_direction']);
                break;
        }

        $members = $query->paginate($perPage)->appends($params);

        $members->through(fn (PartyMember $member): array => [
            'id' => $member->id,
            'nickname' => $member->user->nickname,
            'role' => $member->role->name,
            'createdAt' => $this->when($member->created_at),
            'url' => route('parties.users.show', [$party->code, $member->id]),
        ]);

        return Inertia::render('Parties/Members/Index', [
            'party' => $this->partyProps($party, true),
            'playerState' => $party->getState(),
            'roles' => PartyMemberRole::all()->map(fn (PartyMemberRole $role): array => ['code' => $role->code, 'name' => $role->name])->values(),
            'filters' => $params,
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
            'party' => $this->partyProps($party, true),
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
            'party' => $this->partyProps($party, $party->canBeManagedBy($request->user())),
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
     * @return array{code: string, name: string, canManage: bool}
     */
    private function partyProps(Party $party, bool $canManage): array
    {
        return ['code' => $party->code, 'name' => $party->name, 'canManage' => $canManage];
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
