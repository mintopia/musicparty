<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyRequest;
use App\Models\Party;
use Illuminate\Http\Request;
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

    public function show(Party $party)
    {
        return view('parties.show', [
            'party' => $party,
        ]);
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
        $party->explicit = (bool)$request->input('explicit');
        $party->downvotes = (bool)$request->input('downvotes');
        $party->process_requests = (bool)$request->input('process_requests');
        $party->queue = (bool)$request->input('queue');
        $party->force = (bool)$request->input('force');
        $party->device_name = null;
        $party->device_id = $request->input('device_id');
        if ($request->has('max_song_length') && $request->input('max_song_length') > 0) {
            $party->max_song_length = $request->input('max_song_length');
        }
        $party->save();
    }
}
