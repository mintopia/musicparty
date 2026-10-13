<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyPlayYouTubeRequest;
use App\Http\Requests\PartyRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Jobs\PartyPlayYouTubeVideo;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\SpotifySearchService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function create(Request $request): RedirectResponse|View
    {
        // Check if they have Spotify links
        if (! $this->resolveAuthenticatedUser($request)->hasSpotifyLinks()) {
            return response()->redirectToRoute('user.spotify');
        }

        $party = new Party;

        return view('parties.create', [
            'party' => $party,
            'mods' => Mod::all(),
            'playlists' => $this->resolveAuthenticatedUser($request)->getPlaylists(),
            'devices' => $this->resolveAuthenticatedUser($request)->getDevices(),
        ]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $party = new Party;
        $party->user()->associate($this->resolveAuthenticatedUser($request));
        $this->updateObject($party, $request);

        return response()->redirectToRoute('parties.show', $party->code)->with('successMessage', 'The party has been created');
    }

    public function show(UpcomingSongAugmentService $augmentService, Request $request, Party $party): View
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $upcomingSongs = $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNull('queued_at')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->take(20)
            ->get();

        $augmentData = $augmentService->augmentCollection($upcomingSongs, $this->resolveAuthenticatedUser($request));
        $upcoming = $upcomingSongs->map(function (UpcomingSong $song) use ($request, $augmentData) {
            $resource = new UpcomingSongResource($song);
            $resource->augment($augmentData[$song->id] ?? null);

            return $resource->toArray($request);
        });

        return view('parties.show', [
            'party' => $party,
            'member' => $member,
            'upcoming' => $upcoming->toArray(),
            'canManage' => $party->canBeManagedBy($this->resolveAuthenticatedUser($request)),
        ]);
    }

    public function player(Request $request, Party $party): View
    {
        return view('parties.player', [
            'party' => $party,
            'canManage' => $party->canBeManagedBy($this->resolveAuthenticatedUser($request)),
        ]);
    }

    public function tv(Party $party): View
    {
        return view('parties.tv', [
            'party' => $party,
        ]);
    }

    public function youtube(Request $request, Party $party): View
    {
        return view('parties.youtube', [
            'party' => $party,
            'canManage' => $party->canBeManagedBy($this->resolveAuthenticatedUser($request)),
        ]);
    }

    public function youtube_play(PartyPlayYouTubeRequest $request, Party $party): RedirectResponse
    {
        $videoId = $request->videoId();
        PartyPlayYouTubeVideo::dispatch($party, (string) $videoId)->afterResponse();

        return response()->redirectToRoute('parties.youtube', ['party' => $party->code])
            ->with('successMessage', 'Video requested');
    }

    public function ytplayer(Party $party): View
    {
        return view('parties.ytplayer', [
            'party' => $party,
        ]);
    }

    public function search(SearchRequest $request, Party $party): View
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));

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
            'canManage' => $party->canBeManagedBy($this->resolveAuthenticatedUser($request)),
            'params' => (object) $params,
            'results' => $results,
        ]);
    }

    public function edit(Party $party): View
    {
        return view('parties.edit', [
            'party' => $party,
            'mods' => Mod::all(),
            'canManage' => true,
            'playlists' => $party->user->getPlaylists(),
            'devices' => $party->user->getDevices(),
        ]);
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
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
        $party->allow_requests = (bool) $request->input('allow_requests');
        $party->active = (bool) $request->input('active');
        $party->explicit = (bool) $request->input('explicit');
        $party->downvotes = (bool) $request->input('downvotes');
        $party->force = (bool) $request->input('force');
        $party->poll = (bool) $request->input('poll');
        $party->show_qrcode = (bool) $request->input('show_qrcode');
        $party->device_name = null;
        $party->device_id = $request->input('device_id');
        $party->weighted = (bool) $request->input('weighted');
        if ($request->input('downvotes_per_hour')) {
            $party->downvotes_per_hour = $request->input('downvotes_per_hour');
        } else {
            $party->downvotes_per_hour = null;
        }
        if ($request->input('max_requests')) {
            $party->max_requests = $request->input('max_requests');
        } else {
            $party->max_requests = null;
        }
        if ($request->has('min_song_length') && $request->input('min_song_length') > 0) {
            $party->min_song_length = $request->input('min_song_length');
        }
        if ($request->has('max_song_length') && $request->input('max_song_length') > 0) {
            $party->max_song_length = $request->input('max_song_length');
        }
        if ($request->has('no_repeat_interval') && $request->input('no_repeat_interval') > 0) {
            $party->no_repeat_interval = $request->input('no_repeat_interval');
        }
        if ($request->has('history_playlist_id') && $request->input('history_playlist_id') !== 'none') {
            $party->history_playlist_id = $request->input('history_playlist_id');
        } else {
            $party->history_playlist_id = null;
        }
        $party->save();

        $modSettings = ModSetting::wherePrivate(false)->with('mod')->get();
        foreach ($modSettings as $modSetting) {
            $currentValue = $party->getModSetting($modSetting->mod, $modSetting->code);
            $value = $request->input("mods_{$modSetting->mod->code}_{$modSetting->code}");
            if ($value !== $currentValue) {
                $party->setModSetting($modSetting->mod, $modSetting->code, $value);
            }
        }
    }
}
