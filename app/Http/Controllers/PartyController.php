<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Requests\PartyPlayYouTubeRequest;
use App\Http\Requests\PartyRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\TrackAnnotationResource;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Jobs\PartyPlayYouTubeVideo;
use App\Jobs\PartyUpdate;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\ModSettingsStore;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyProviderOptions;
use App\Services\PlaybackControl;
use App\Services\Requests\TrackEligibility;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function create(Request $request, PartyProviderOptions $options): RedirectResponse|View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $factory = app(MusicProviderFactory::class);
        $providerId = $request->query('provider_id');
        $providerId = is_string($providerId) && in_array($providerId, $factory->selectableIds(), true)
            ? $providerId
            : MusicProviderFactory::DEFAULT_PROVIDER_ID;

        // Check they have a link for the chosen provider
        if ($providerId === MusicProviderFactory::DEFAULT_PROVIDER_ID && ! $user->hasSpotifyLinks()) {
            return response()->redirectToRoute('user.spotify');
        }

        $draft = $options->draft($user, $providerId);

        return view('parties.create', [
            'party' => new Party,
            'mods' => Mod::all(),
            'playlists' => $options->playlists($draft),
            'devices' => $options->devices($draft),
            'providerIds' => $factory->selectableIds(),
        ]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $party = new Party;
        $party->user()->associate($this->resolveAuthenticatedUser($request));
        $party->provider_id = $request->string('provider_id')->toString();
        $this->updateObject($party, $request);
        PartyUpdate::dispatch($party);

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
            'canManage' => $party->canBeManagedBy($request->user()),
            'spotifyNeedsRelink' => $party->canBeManagedBy($request->user()) && $party->user->spotifyNeedsRelink(),
        ]);
    }

    public function player(Request $request, Party $party): View
    {
        return view('parties.player', [
            'party' => $party,
            'canManage' => $party->canBeManagedBy($request->user()),
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
            'canManage' => $party->canBeManagedBy($request->user()),
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

    public function search(SearchRequest $request, Party $party, MusicProviderFactory $factory, TrackEligibility $eligibility): Response
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
            try {
                $found = $factory->forParty($party, Purpose::Search)->searchTracks((string) $request->input('query'), $page, (int) $perPage);
                $items = $eligibility->annotate($party, $member, $found->items);
                $results = new LengthAwarePaginator($items, $found->total, (int) $perPage, $page)
                    ->setPath(route('parties.search', $party->code))
                    ->appends($params)
                    ->through(fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve());
            } catch (ProviderRelinkRequiredException) {
                session()->now('errorMessage', 'Spotify needs re-linking before search is available.');
            } catch (ProviderBackoffException) {
                session()->now('errorMessage', 'Spotify is busy right now. Please try again shortly.');
            }
        }

        return Inertia::render('Parties/Search', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($request->user()),
            ],
            'params' => ['query' => $params['query'], 'perPage' => (int) $perPage],
            'results' => $results,
            'playerState' => $party->getState(),
        ]);
    }

    public function edit(Party $party, PartyProviderOptions $options): View
    {
        return view('parties.edit', [
            'party' => $party,
            'mods' => Mod::all(),
            'canManage' => true,
            'playlists' => $options->playlists($party),
            'devices' => $options->devices($party),
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
        $deviceId = $request->input('device_id') ?: null;
        if ($deviceId === null) {
            $party->device_name = null;
        } elseif ($deviceId !== $party->device_id || $party->device_name === null) {
            $party->device_id = $deviceId;
            $party->device_name = app(PlaybackControl::class)->deviceName($party, $deviceId);
        }
        $party->device_id = $deviceId;
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

        $store = app(ModSettingsStore::class);
        $modSettings = ModSetting::wherePrivate(false)->with('mod')->get();
        foreach ($modSettings as $modSetting) {
            $currentValue = $store->value($party, $modSetting->mod, $modSetting->code);
            $value = $request->input("mods_{$modSetting->mod->code}_{$modSetting->code}");
            if ($value !== $currentValue) {
                $store->set($party, $modSetting->mod, $modSetting->code, $value);
            }
        }
    }
}
