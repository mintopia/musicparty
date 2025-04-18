<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyMemberSearchRequest;
use App\Http\Requests\PartyMemberUpdateRequest;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use Illuminate\Http\Request;

class PartyMemberController extends Controller
{
    public function index(PartyMemberSearchRequest $request, Party $party)
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
                $query = $query->join('users', 'users.id', '=', 'party_members.user_id')->orderBy('users.nickname', $params['direction']);
                break;
        }

        $members = $query->paginate($perPage)->appends($params);
        return view('partymembers.index', [
            'party' => $party,
            'canManage' => true,
            'members' => $members,
            'roles' => PartyMemberRole::all(),
            'filters' => (object)$params,
            'params' => $params,
        ]);
    }

    public function show(Request $request, Party $party, PartyMember $user)
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

        return view('partymembers.show', [
            'party' => $party,
            'canManage' => true,
            'member' => $user,
            'votes' => $votes,
            'filters' => (object)$params,
            'params' => $params,
            'types' => [(object)[
                'code' => 'downvote',
                'name' => 'Downvote',
            ], (object)[
                'code' => 'upvote',
                'name' => 'Upvote',
            ]],
        ]);
    }

    public function edit(Request $request, Party $party, PartyMember $user)
    {
        return view('partymembers.edit', [
            'party' => $party,
            'canManage' => $party->canBeManagedBy($request->user()),
            'member' => $user,
            'roles' => PartyMemberRole::all(),
        ]);
    }

    public function update(PartyMemberUpdateRequest $request, Party $party, PartyMember $user)
    {
        $role = PartyMemberRole::whereCode($request->input('role'))->first();
        $user->role()->associate($role);
        $user->save();
        return response()->redirectToRoute('parties.users.show', [$party->code, $user->id])->with('successMessage', 'The user has been updated');
    }
}
