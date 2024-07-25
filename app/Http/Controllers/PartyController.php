<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Services\SpotifySearchService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use SpotifyWebAPI\SpotifyWebAPI;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function create(Request $request)
    {
        // Check if they have Spotify links
        if (!$request->user()->hasSpotifyLinks()) {
            return response()->redirectToRoute('user.spotify');
        }

        $party = new Party;
        return view('parties.create', [
            'party' => $party,
            'playlists' => $request->user()->getPlaylists(),
            'devices' => $request->user()->getDevices(),
        ]);
    }

    public function store(PartyRequest $request)
    {
        $party = new Party();
        $party->user()->associate($request->user());
        $this->updateObject($party, $request);
        return response()->redirectToRoute('parties.show', $party->code)->with('successMessage', 'The party has been created');
    }

    public function show(UpcomingSongAugmentService $augmentService, Request $request, Party $party)
    {
        $member = $party->getMember($request->user());
        $upcomingSongs = $party->upcoming()
            ->with(['user', 'song'])
            ->whereNull('queued_at')
            ->orderBy('score', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->take(20)
            ->get();

        $augmentData = $augmentService->augmentCollection($upcomingSongs, $request->user());
        $upcoming = $upcomingSongs->map(function(UpcomingSong $song) use ($request, $augmentData) {
            $resource = new UpcomingSongResource($song);
            $resource->augment($augmentData[$song->id] ?? null);
            return $resource->toArray($request);
        });

        return view('parties.show', [
            'party' => $party,
            'member' => $member,
            'upcoming' => $upcoming->toArray(),
            'canManage' => $party->canBeManagedBy($request->user()),
        ]);
    }

    public function tv(Party $party)
    {
        return view('parties.tv', [
            'party' => $party,
        ]);
    }

    public function search(SearchRequest $request, Party $party)
    {
        $member = $party->getMember($request->user());

        $params = [
            'query' => $request->input('query'),
        ];
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = $request->input('perPage', 20);
        if ($perPage !== 20) {
            $params['perPage'] = $perPage;
        }

        $results = null;
        if ($params['query']) {
            $searchService = new SpotifySearchService($party, $member);
            $results = $searchService->search($request->input('query'), $page, $perPage)->setPath(route('parties.search', $party->code))->appends($params);
        }

        return view('parties.search', [
            'party' => $party,
            'member' => $member,
            'canManage' => $party->canBeManagedBy($request->user()),
            'params' => (object)$params,
            'results' => $results,
        ]);
    }

    public function edit(Party $party)
    {
        return view('parties.edit', [
            'party' => $party,
            'canManage' => true,
            'playlists' => $party->user->getPlaylists(),
            'devices' => $party->user->getDevices(),
        ]);
    }

    public function update(PartyRequest $request, Party $party)
    {
        $this->updateObject($party, $request);
        $party->save();
        return response()->redirectToRoute('parties.show', $party->code)->with('successMessage', 'The party has been updated');
    }

    protected function updateObject(Party $party, Request $request): void
    {
        $party->name = $request->input('name');
        if ($request->input('backup_playlist_id') === 'other') {
            $party->backup_playlist_id = $request->input('custom_backup_playlist_id');
        } else {
            $party->backup_playlist_id = $request->input('backup_playlist_id');
        }
        $party->allow_requests = (bool)$request->input('allow_requests');
        $party->active = (bool)$request->input('active');
        $party->explicit = (bool)$request->input('explicit');
        $party->downvotes = (bool)$request->input('downvotes');
        $party->queue = (bool)$request->input('queue');
        $party->force = (bool)$request->input('force');
        $party->poll = (bool)$request->input('poll');
        $party->show_qrcode = (bool)$request->input('show_qrcode');
        $party->device_name = null;
        $party->device_id = $request->input('device_id');
        if ($request->input('downvotes_per_hour')) {
            $party->downvotes_per_hour = $request->input('downvotes_per_hour');
        } else {
            $party->downvotes_per_hour = null;
        }
        if ($request->has('max_song_length') && $request->input('max_song_length') > 0) {
            $party->max_song_length = $request->input('max_song_length');
        }
        if ($request->has('no_repeat_interval') && $request->input('no_repeat_interval') > 0) {
            $party->no_repeat_interval = $request->input('no_repeat_interval');
        }
        $party->save();
    }
}
