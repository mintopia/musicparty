<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Requests\PartyRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\TrackAnnotationResource;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyProviderOptions;
use App\Services\PartyTimezone;
use App\Services\Playback\ContextPlaylist;
use App\Services\PlaybackControl;
use App\Services\Requests\TrackEligibility;
use App\Services\UpcomingSongAugmentService;
use App\Support\LeakyBucket;
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

    public function create(Request $request, PartyProviderOptions $options): RedirectResponse|Response
    {
        $user = $this->resolveAuthenticatedUser($request);
        $factory = app(MusicProviderFactory::class);
        $providerId = $request->query('provider_id');
        $providerId = is_string($providerId) && in_array($providerId, $factory->selectableIds(), true)
            ? $providerId
            : MusicProviderFactory::DEFAULT_PROVIDER_ID;

        if (! $factory->forUser($providerId, $user, Purpose::Control)->isLinked()) {
            return response()->redirectToRoute('user.spotify');
        }

        $draft = $options->draft($user, $providerId);

        return Inertia::render('Parties/Create', $this->formProps($options, $draft, $user) + [
            'party' => $this->formValues(new Party),
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

    public function show(UpcomingSongAugmentService $augmentService, Request $request, Party $party): Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $upcomingSongs = $party->upcoming()
            ->with(['user', 'song', 'song.album', 'song.artists'])
            ->whereNull('queued_at')
            ->whereNull('flagged_at')
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

        $canManage = $party->canBeManagedBy($request->user());

        return Inertia::render('Parties/Show', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $canManage,
                'weighted' => (bool) $party->weighted,
            ],
            'member' => ['id' => $member->id],
            'upcoming' => $upcoming->values()->all(),
            'timezone' => app(PartyTimezone::class)->for($party),
            'spotifyNeedsRelink' => $canManage && app(MusicProviderFactory::class)->partyNeedsRelink($party),
            'relinkUrl' => route('linkedaccounts.create', 'spotify'),
            'searchUrl' => route('parties.search', $party->code),
            'playerState' => $party->getState(),
        ]);
    }

    public function tv(Party $party): Response
    {
        return Inertia::render('Parties/Tv', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'url' => route('parties.show', $party->code),
            ],
            'state' => $party->getState(...),
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
        if ($params['query'] && ! $this->withinSearchLimit($party, $member)) {
            session()->now('errorMessage', 'The music provider is busy right now. Please try again shortly.');
        } elseif ($params['query']) {
            try {
                $found = $factory->forParty($party, Purpose::Search)->searchTracks((string) $request->input('query'), $page, (int) $perPage);
                $items = $eligibility->annotate($party, $member, $found->items);
                $results = new LengthAwarePaginator($items, $found->total, (int) $perPage, $page)
                    ->setPath(route('parties.search', $party->code))
                    ->appends($params)
                    ->through(fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve());
            } catch (ProviderRelinkRequiredException) {
                session()->now('errorMessage', 'The music provider needs re-linking before search is available.');
            } catch (ProviderBackoffException) {
                session()->now('errorMessage', 'The music provider is busy right now. Please try again shortly.');
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

    public function album(SearchRequest $request, Party $party, string $album, MusicProviderFactory $factory, TrackEligibility $eligibility): Response|\Symfony\Component\HttpFoundation\Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $query = $request->input('query');

        $props = [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($request->user()),
            ],
            'query' => is_string($query) ? $query : null,
            'album' => null,
            'tracks' => null,
            'retry' => false,
            'playerState' => $party->getState(),
        ];

        if (! $this->withinSearchLimit($party, $member)) {
            return Inertia::render('Parties/Album', ['retry' => true] + $props);
        }

        try {
            $found = $factory->forParty($party, Purpose::Search)->getAlbum($album);
        } catch (ProviderRelinkRequiredException) {
            session()->now('errorMessage', 'The music provider needs re-linking before albums are available.');

            return Inertia::render('Parties/Album', $props);
        } catch (ProviderBackoffException) {
            return Inertia::render('Parties/Album', ['retry' => true] + $props);
        }

        if ($found === null) {
            return Inertia::render('Parties/Album', $props)->toResponse($request)->setStatusCode(404);
        }

        $annotations = $eligibility->annotate($party, $member, $found->tracks);
        $tracks = new LengthAwarePaginator($annotations, count($annotations), max(count($annotations), 1), 1)
            ->through(fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve());

        return Inertia::render('Parties/Album', [
            'album' => [
                'id' => $found->ref->id,
                'name' => $found->ref->name,
                'image_url' => $found->ref->imageUrl,
                'artists' => array_map(fn ($artist): array => ['id' => $artist->id, 'name' => $artist->name], $found->artists),
                'release_year' => $found->releaseDate !== null ? substr($found->releaseDate, 0, 4) : null,
                'track_count' => $found->totalTracks,
            ],
            'tracks' => $tracks,
        ] + $props);
    }

    public function artist(SearchRequest $request, Party $party, string $artist, MusicProviderFactory $factory, TrackEligibility $eligibility): Response|\Symfony\Component\HttpFoundation\Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $query = $request->input('query');

        $props = [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($request->user()),
            ],
            'query' => is_string($query) ? $query : null,
            'artist' => null,
            'tracks' => null,
            'albums' => null,
            'retry' => false,
            'playerState' => $party->getState(),
        ];

        if (! $this->withinSearchLimit($party, $member)) {
            return Inertia::render('Parties/Artist', ['retry' => true] + $props);
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 12;

        try {
            $provider = $factory->forParty($party, Purpose::Search);
            $found = $provider->getArtist($artist);
            if ($found !== null) {
                $topTracks = $provider->getArtistTopTracks($artist);
                $albumPage = $provider->getArtistAlbums($artist, $page, $perPage);
            }
        } catch (ProviderRelinkRequiredException) {
            session()->now('errorMessage', 'The music provider needs re-linking before artists are available.');

            return Inertia::render('Parties/Artist', $props);
        } catch (ProviderBackoffException) {
            return Inertia::render('Parties/Artist', ['retry' => true] + $props);
        }

        if ($found === null) {
            return Inertia::render('Parties/Artist', $props)->toResponse($request)->setStatusCode(404);
        }

        $annotations = $eligibility->annotate($party, $member, $topTracks);
        $tracks = new LengthAwarePaginator($annotations, count($annotations), max(count($annotations), 1), 1)
            ->through(fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve());
        $albums = new LengthAwarePaginator($albumPage->items, $albumPage->total, $perPage, $page)
            ->setPath(route('parties.artists.show', ['party' => $party->code, 'artist' => $artist]))
            ->appends(array_filter(['query' => $props['query']]))
            ->through(fn ($album): array => ['id' => $album->id, 'name' => $album->name, 'image_url' => $album->imageUrl]);

        return Inertia::render('Parties/Artist', [
            'artist' => [
                'id' => $found->ref->id,
                'name' => $found->ref->name,
                'image_url' => $found->imageUrl,
            ],
            'tracks' => $tracks,
            'albums' => $albums,
        ] + $props);
    }

    protected function withinSearchLimit(Party $party, PartyMember $member): bool
    {
        return new LeakyBucket(
            "search:{$party->getKey()}:{$member->user_id}",
            (int) config('musicparty.search_burst'),
            (float) config('musicparty.search_leak_per_second'),
        )->attempt();
    }

    public function edit(Request $request, Party $party, PartyProviderOptions $options): Response
    {
        $reverbApp = config('reverb.apps.apps.0');
        $isAdmin = $request->user()?->can('admin') ?? false;

        return Inertia::render('Parties/Edit', $this->formProps($options, $party, $this->resolveAuthenticatedUser($request)) + [
            'party' => $this->formValues($party) + ['code' => $party->code],
            'providerName' => ucfirst((string) $party->provider_id),
            'playerState' => $party->getState(),
            'encore' => [
                'host' => $request->getHost(),
                'key' => $reverbApp['key'] ?? null,
                'channel' => "private-encore.{$party->code}",
                'secret' => $isAdmin ? ($reverbApp['secret'] ?? null) : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formProps(PartyProviderOptions $options, Party $source, User $user): array
    {
        return [
            'playlists' => array_map(fn ($playlist): array => ['id' => $playlist->id, 'name' => $playlist->name], $options->playlists($source)),
            'devices' => array_map(fn ($device): array => ['id' => $device->id, 'name' => $device->name], $options->devices($source)),
            'contextPlaylistName' => $this->contextPlaylistName($source),
            'defaultCountry' => $user->market ?: 'GB',
            'instanceTimezone' => app(PartyTimezone::class)->instance(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ];
    }

    protected function contextPlaylistName(Party $party): ?string
    {
        if (! $party->exists || $party->context_playlist_id === null) {
            return null;
        }

        try {
            return app(MusicProviderFactory::class)->forParty($party, Purpose::Control)->ownedPlaylist($party->context_playlist_id)?->name;
        } catch (ProviderRelinkRequiredException|ProviderBackoffException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formValues(Party $party): array
    {
        return [
            'name' => $party->name,
            'country' => $party->country,
            'timezone' => $party->timezone,
            'backup_playlist_id' => $party->backup_playlist_id,
            'history_playlist_id' => $party->history_playlist_id,
            'context_playlist_id' => $party->context_playlist_id,
            'device_id' => $party->device_id,
            'active' => (bool) $party->active,
            'allow_requests' => (bool) $party->allow_requests,
            'downvotes' => (bool) $party->downvotes,
            'weighted' => (bool) $party->weighted,
            'show_qrcode' => (bool) $party->show_qrcode,
            'flag_requests' => (bool) $party->flag_requests,
            'explicit' => (bool) $party->explicit,
            'poll' => (bool) $party->poll,
            'force' => (bool) $party->force,
            'min_song_length' => $party->min_song_length,
            'max_song_length' => $party->max_song_length,
            'no_repeat_interval' => $party->no_repeat_interval,
            'downvotes_per_hour' => $party->downvotes_per_hour,
            'max_requests' => $party->max_requests,
        ];
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
        if ($request->filled('country')) {
            $party->country = strtoupper($request->string('country')->toString());
        } elseif (! $party->exists) {
            $party->country = strtoupper($party->user->market ?: 'GB');
        }
        $party->timezone = $request->input('timezone') ?: null;
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
        $party->flag_requests = (bool) $request->input('flag_requests');
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
        if ($party->exists && $request->has('context_playlist_id')) {
            $contextPlaylistId = $request->input('context_playlist_id') ?: null;
            if ($contextPlaylistId !== $party->context_playlist_id) {
                app(ContextPlaylist::class)->forgetVerified($party);
            }
            $party->context_playlist_id = $contextPlaylistId;
        }
        if ($request->has('history_playlist_id') && $request->input('history_playlist_id') !== 'none') {
            $party->history_playlist_id = $request->input('history_playlist_id');
        } else {
            $party->history_playlist_id = null;
        }
        $party->save();
    }
}
