<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Exceptions\ProviderUnavailableException;
use App\Http\Requests\PartyRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\TrackAnnotationResource;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\ModSettingsStore;
use App\Services\Music\Data\AlbumSummary;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\SearchOverview;
use App\Services\Music\Data\SearchType;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PartyListing;
use App\Services\PartyProviderOptions;
use App\Services\PartyTimezone;
use App\Services\Playback\ContextPlaylist;
use App\Services\PlaybackControl;
use App\Services\Requests\TrackEligibility;
use App\Services\Search\PartySearchSuggestions;
use App\Services\UpcomingSongAugmentService;
use App\Support\LeakyBucket;
use App\Support\QrCode;
use App\Support\SearchRelevance;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    private const array SETTINGS_TABS = ['general', 'requests', 'playback', 'screens', 'mods'];

    private const int SEARCH_PAGE_SIZE = 10;

    private const int SEARCH_OVERVIEW_LIMIT = 10;

    private const int SEARCH_OVERVIEW_TRACKS = 4;

    private const int SUGGESTED_ARTISTS = 6;

    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function index(Request $request, PartyListing $listing): Response
    {
        return Inertia::render('Parties/Index', [
            'parties' => $listing->forMember($this->resolveAuthenticatedUser($request)),
        ]);
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
            'hostName' => $user->nickname,
            'providerIds' => $factory->selectableIds(),
            'createdParty' => $request->session()->get('createdParty'),
        ]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $party = new Party;
        $party->user()->associate($this->resolveAuthenticatedUser($request));
        $party->provider_id = $request->string('provider_id')->toString();
        $this->updateObject($party, $request);
        PartyUpdate::dispatch($party);

        $joinUrl = route('parties.show', $party->code);

        return response()->redirectToRoute('parties.create', ['provider_id' => $party->provider_id])->with('createdParty', [
            'code' => $party->code,
            'name' => $party->name,
            'joinUrl' => $joinUrl,
            'qr' => QrCode::svg($joinUrl),
        ]);
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
        $canModerate = $request->user()?->can('moderate', $party) ?? false;

        return Inertia::render('Parties/Show', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $canManage,
                'canModerate' => $canModerate,
                'weighted' => (bool) $party->weighted,
            ],
            'member' => ['id' => $member->id],
            'upcoming' => $upcoming->values()->all(),
            'timezone' => app(PartyTimezone::class)->for($party),
            'spotifyNeedsRelink' => $canManage && app(MusicProviderFactory::class)->partyNeedsRelink($party),
            'relinkUrl' => route('linkedaccounts.create', 'spotify'),
            'searchUrl' => route('parties.search', $party->code),
            'playerState' => $party->getState(),
            'myRating' => $party->ratingBy($this->resolveAuthenticatedUser($request)),
        ]);
    }

    public function tv(Party $party): Response
    {
        return Inertia::render('Parties/Tv', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'url' => route('parties.show', $party->code),
                'qrSvg' => QrCode::svg(route('parties.show', $party->code)),
            ],
            'state' => $party->getState(...),
        ]);
    }

    public function search(SearchRequest $request, Party $party, MusicProviderFactory $factory, TrackEligibility $eligibility, PartySearchSuggestions $suggestions): Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));

        $query = $request->input('query');
        $type = SearchType::tryFrom((string) $request->input('type'));
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = max(1, min((int) $request->input('perPage', self::SEARCH_PAGE_SIZE), SpotifyMusicProvider::SEARCH_MAX_LIMIT));

        $params = ['query' => $query];
        if ($type !== null) {
            $params['type'] = $type->value;
        }
        if ($perPage !== self::SEARCH_PAGE_SIZE) {
            $params['perPage'] = $perPage;
        }

        $results = null;
        if ($query) {
            $text = (string) $query;
            $results = Inertia::defer(
                fn (): array => $this->searchResults($party, $member, $factory, fn (MusicProvider $provider): array => $type === null
                    ? $this->searchOverview($party, $member, $eligibility, $provider, $text)
                    : $this->searchSingleType($party, $member, $eligibility, $provider, $type, $text, $page, $perPage, $params)),
                rescue: true,
            );
        }

        return Inertia::render('Parties/Search', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'downvotes' => (bool) $party->downvotes,
                'canManage' => $party->canBeManagedBy($request->user()),
                'canModerate' => $request->user()?->can('moderate', $party) ?? false,
            ],
            'params' => ['query' => $query, 'perPage' => $perPage, 'type' => $type?->value],
            'results' => $results,
            'suggestions' => $query ? null : [
                'nowPlaying' => $suggestions->nowPlaying($party),
                'artists' => $suggestions->topArtists($party, self::SUGGESTED_ARTISTS),
            ],
            'playerState' => $party->getState(),
        ]);
    }

    /**
     * @param  Closure(MusicProvider): array<string, mixed>  $search
     * @return array<string, mixed>
     */
    protected function searchResults(Party $party, PartyMember $member, MusicProviderFactory $factory, Closure $search): array
    {
        if (! $this->withinSearchLimit($party, $member)) {
            return $this->searchError('busy', 'The music provider is busy right now. Please try again shortly.');
        }

        try {
            return $search($factory->forParty($party, Purpose::Search));
        } catch (ProviderRelinkRequiredException) {
            return $this->searchError('relink', 'The music provider needs re-linking before search is available.', false);
        } catch (ProviderBackoffException) {
            return $this->searchError('busy', 'The music provider is busy right now. Please try again shortly.');
        } catch (ProviderUnavailableException $e) {
            return $this->searchError(
                $e->timedOut ? 'timeout' : 'unavailable',
                $e->timedOut ? "{$factory->forParty($party, Purpose::Search)->displayName()} didn't answer in time." : 'The music provider could not be reached.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchOverview(Party $party, PartyMember $member, TrackEligibility $eligibility, MusicProvider $provider, string $query): array
    {
        $found = $provider->searchOverview($query, self::SEARCH_OVERVIEW_LIMIT);

        $topArtist = $this->exactArtistMatch($query, $found->artists->items);
        $tracks = $found->tracks->items;
        $topTrack = null;
        if ($topArtist === null && $tracks !== []) {
            $topTrack = $tracks[0];
        }
        $tracks = array_slice($tracks, 0, self::SEARCH_OVERVIEW_TRACKS);

        $annotated = array_map(
            fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve(),
            $eligibility->annotate($party, $member, $tracks),
        );
        $top = null;
        if ($topArtist !== null) {
            $top = ['kind' => 'artist', 'artist' => $this->artistResult($topArtist)];
        } elseif ($topTrack !== null) {
            $top = ['kind' => 'track', 'track' => $annotated[0]];
        }

        $albums = array_map($this->albumResult(...), $found->albums->items);
        $artists = array_map($this->artistResult(...), $found->artists->items);

        return [
            'view' => 'overview',
            'top' => $top,
            'tracks' => $annotated,
            'albums' => $albums,
            'artists' => $artists,
            'empty' => $top === null && $annotated === [] && $albums === [] && $artists === [],
            'weak' => SearchRelevance::isWeak($query, $this->overviewCandidates($found)),
        ];
    }

    /**
     * @return list<string>
     */
    protected function overviewCandidates(SearchOverview $found): array
    {
        $names = fn (array $artists): string => implode(' ', array_map(fn (ArtistRef $artist): string => $artist->name, $artists));

        return [
            ...array_map(fn (Track $track): string => "{$track->name} {$names($track->artists)} {$track->album->name}", $found->tracks->items),
            ...array_map(fn (AlbumSummary $album): string => "{$album->ref->name} {$names($album->artists)}", $found->albums->items),
            ...array_map(fn (Artist $artist): string => $artist->ref->name, $found->artists->items),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function searchSingleType(Party $party, PartyMember $member, TrackEligibility $eligibility, MusicProvider $provider, SearchType $type, string $query, int $page, int $perPage, array $params): array
    {
        $found = match ($type) {
            SearchType::Track => $provider->searchTracks($query, $page, $perPage),
            SearchType::Album => $provider->searchAlbums($query, $page, $perPage),
            SearchType::Artist => $provider->searchArtists($query, $page, $perPage),
        };

        $items = match ($type) {
            SearchType::Track => array_map(
                fn ($annotation): array => new TrackAnnotationResource($annotation)->resolve(),
                $eligibility->annotate($party, $member, $found->items),
            ),
            SearchType::Album => array_map($this->albumResult(...), $found->items),
            SearchType::Artist => array_map($this->artistResult(...), $found->items),
        };

        $total = min($found->total, SpotifyMusicProvider::SEARCH_MAX_OFFSET);
        $paginator = new LengthAwarePaginator($items, $total, $found->perPage, $found->page)
            ->setPath(route('parties.search', $party->code))
            ->appends($params);

        return [
            'view' => 'type',
            'type' => $type->value,
            'total' => $total,
            'capped' => $found->total > SpotifyMusicProvider::SEARCH_MAX_OFFSET,
            'items' => $paginator->toArray(),
        ];
    }

    /**
     * @param  list<Artist>  $artists
     */
    protected function exactArtistMatch(string $query, array $artists): ?Artist
    {
        $needle = $this->normaliseName($query);
        foreach ($artists as $artist) {
            if ($this->normaliseName($artist->ref->name) === $needle) {
                return $artist;
            }
        }

        return null;
    }

    protected function normaliseName(string $name): string
    {
        $lower = mb_strtolower(trim($name));
        $stripped = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $lower) ?? $lower;
        $withoutArticle = preg_replace('/^the\s+/u', '', trim($stripped)) ?? $stripped;
        $normalised = trim(preg_replace('/\s+/u', ' ', $withoutArticle) ?? $withoutArticle);

        return $normalised === '' ? $lower : $normalised;
    }

    /**
     * @return array{id: string, name: string, image_url: string|null, album_type: string, artists: list<array{id: string, name: string}>, release_year: string|null}
     */
    protected function albumResult(AlbumSummary $album): array
    {
        return [
            'id' => $album->ref->id,
            'name' => $album->ref->name,
            'image_url' => $album->ref->imageUrl,
            'album_type' => $album->albumType,
            'artists' => array_map(fn (ArtistRef $artist): array => ['id' => $artist->id, 'name' => $artist->name], $album->artists),
            'release_year' => $album->releaseYear,
        ];
    }

    /**
     * @return array{id: string, name: string, image_url: string|null}
     */
    protected function artistResult(Artist $artist): array
    {
        return ['id' => $artist->ref->id, 'name' => $artist->ref->name, 'image_url' => $artist->imageUrl];
    }

    /**
     * @return array{error: array{code: string, message: string, retryable: bool}}
     */
    protected function searchError(string $code, string $message, bool $retryable = true): array
    {
        return ['error' => ['code' => $code, 'message' => $message, 'retryable' => $retryable]];
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
                'canModerate' => $request->user()?->can('moderate', $party) ?? false,
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
                'canModerate' => $request->user()?->can('moderate', $party) ?? false,
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

    public function edit(Request $request, Party $party, PartyProviderOptions $options, ModSettingsStore $mods): Response
    {
        $reverbApp = config('reverb.apps.apps.0');
        $isAdmin = $request->user()?->can('admin') ?? false;

        return Inertia::render('Parties/Edit', $this->formProps($options, $party, $this->resolveAuthenticatedUser($request)) + [
            'party' => $this->formValues($party) + ['code' => $party->code],
            'providerName' => ucfirst((string) $party->provider_id),
            'hostName' => $party->user->nickname,
            'playerState' => $party->getState(),
            'tab' => in_array($request->query('tab'), self::SETTINGS_TABS, true) ? $request->query('tab') : 'general',
            'mods' => fn () => $mods->presentFor($party),
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
            'playlists' => array_map(fn ($playlist): array => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'imageUrl' => $playlist->imageUrl,
                'trackCount' => $playlist->trackCount,
                'ownerName' => $playlist->ownerName,
            ], $options->playlists($source)),
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

        return redirect()->to(url()->previous(route('parties.edit', $party->code)))->with('successMessage', 'The party has been updated');
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
            $party->backup_playlist_id = PartyRequest::normalisePlaylistId((string) $request->input('custom_backup_playlist_id'));
        } else {
            $party->backup_playlist_id = $request->input('backup_playlist_id');
        }
        foreach (['allow_requests', 'active', 'explicit', 'downvotes', 'force', 'poll', 'show_qrcode', 'flag_requests', 'weighted'] as $flag) {
            if ($party->exists || $request->has($flag)) {
                $party->{$flag} = $request->boolean($flag);
            }
        }
        $deviceId = $request->input('device_id') ?: null;
        if ($deviceId === null) {
            $party->device_name = null;
        } elseif ($deviceId !== $party->device_id || $party->device_name === null) {
            $party->device_id = $deviceId;
            $party->device_name = app(PlaybackControl::class)->deviceName($party, $deviceId);
        }
        $party->device_id = $deviceId;
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
