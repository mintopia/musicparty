<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function create(Request $request)
    {
        return view('parties.create', [
            'playlists' => $request->user()->getPlaylists(),
        ]);
    }

    public function store(Request $request)
    {
        $party = new Party;
        $party->user()->associate($request->user());
        $this->updateParty($request, $party);
        $party->save();
        return response()->redirectToRoute('parties.show', $party->code)->with('successMessage', 'The party has been created');
    }

    public function show(Party $party)
    {
        $next = $party->upcoming()
            ->whereNotNull('queued_at')
            ->with(['song', 'song.artists', 'song.album'])
            ->orderBy('queued_at', 'DESC')
            ->first();

        $upcoming = $party->upcoming()
            ->whereNull('queued_at')
            ->with(['song', 'song.artists', 'song.album'])
            ->orderBy('votes', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->paginate();

        return view('parties.show', [
            'party' => $party,
            'upcoming' => $upcoming,
            'next' => $next,
        ]);
    }

    public function tv(Party $party)
    {
        return view('parties.tv', [
            'party' => $party,
        ]);
    }

    public function edit(Party $party)
    {
        return view('parties.edit', [
            'party' => $party,
        ]);
    }

    public function update(Request $request, Party $party)
    {
        $this->updateParty($request, $party);
        return response()->redirectToRoute('parties.show', $party->code)->with('successMessage', 'The party has been updated');
    }

    public function delete(Party $party)
    {
        $party->delete();
        return response()->redirectToRoute('parties.index')->with('successMessage', 'The party has been deleted');
    }

    protected function join(Request $request)
    {
        $code = $request->input('code');
        $party = Party::whereCode($code)->first();
        if ($party) {
            return response()->redirectToRoute('parties.show', $party->code);
        } else {
            return response()->redirectToRoute('home')->with('errorMessage', 'Invalid party code entered');
        }
    }

    protected function updateParty(Request $request, Party $party): Party
    {
        $party->name = $request->input('name');
        $party->backup_playlist_id = $request->input('backup_playlist_id');

        return $party;
    }
}
