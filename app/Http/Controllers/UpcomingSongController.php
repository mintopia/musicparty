<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\UpcomingSongSearchRequest;
use App\Models\Party;
use App\Models\UpcomingSong;
use Illuminate\Http\Request;

class UpcomingSongController extends Controller
{
    public function index(UpcomingSongSearchRequest $request, Party $party)
    {
        $query = $party->upcoming()->withCount('votes')->with(['user', 'song', 'song.album', 'song.artists']);
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
            $query = $query->whereHas('song', function($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['name']}%");
            });
        }

        if ($request->input('album')) {
            $params['album'] = $request->input('album');
            $query = $query->whereHas('song.album', function($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['album']}%");
            });
        }

        if ($request->input('artist')) {
            $params['artist'] = $request->input('artist');
            $query = $query->whereHas('song.artists', function($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['artist']}%");
            });
        }

        if ($request->input('type')) {
            $params['type'] = $request->input('type');
            if ($request->input('type') === 'queued') {
                $query = $query->whereNull('queued_at');
            } else {
                $query = $query->whereNotNull('queued_at');
            }
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
            case 'queued_at':
            case 'score':
                $query = $query->orderBy($params['order'], $params['order_direction']);
                break;
            case 'votes':
                $query = $query->orderBy('votes_count', $params['order_direction']);
                break;
        }

        $songs = $query->paginate($perPage)->appends($params);
        return view('upcomingsongs.index', [
            'party' => $party,
            'canManage' => true,
            'songs' => $songs,
            'types' => [(object)[
                'code' => 'queued',
                'name' => 'Queued',
            ], (object)[
                'code' => 'spotify',
                'name' => 'Sent to Spotify',
            ]],
            'filters' => (object)$params,
            'params' => $params,
        ]);
    }

    public function show(PaginationRequest $request, Party $party, UpcomingSong $song)
    {
        $params = [];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }
        $votes = $song->votes()->orderBy('created_at', 'ASC')->with(['user', 'user.partyMembers'])->paginate($perPage)->appends($params);
        $other = $party->upcoming()->whereSongId($song->song_id)->where('id', '<>', $song->id)->withCount('votes')->orderBy('created_at', 'DESC')->get();
        return view('upcomingsongs.show', [
            'party' => $party,
            'canManage' => true,
            'song' => $song,
            'votes' => $votes,
            'other' => $other,
        ]);
    }
}
