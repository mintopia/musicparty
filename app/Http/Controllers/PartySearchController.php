<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Exceptions\ProviderUnavailableException;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\TrackAnnotationResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\Data\AlbumSummary;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\SearchOverview;
use App\Services\Music\Data\SearchType;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PartyHeaderProps;
use App\Services\Requests\TrackEligibility;
use App\Services\Search\PartySearchSuggestions;
use App\Support\LeakyBucket;
use App\Support\SearchRelevance;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PartySearchController extends Controller
{
    private const int SEARCH_PAGE_SIZE = 10;

    private const int SEARCH_CACHE_SECONDS = 60;

    private const string SEARCH_BUSY_MESSAGE = 'The music provider is busy right now. Please try again shortly.';

    private const int SEARCH_OVERVIEW_LIMIT = 10;

    private const int SEARCH_OVERVIEW_TRACKS = 4;

    private const int SUGGESTED_ARTISTS = 6;

    public function search(SearchRequest $request, Party $party, MusicProviderFactory $factory, TrackEligibility $eligibility, PartyHeaderProps $header, PartySearchSuggestions $suggestions): Response
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
            'party' => $header->for($party, $request->user()),
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
        try {
            return $search($factory->forParty($party, Purpose::Search));
        } catch (ProviderRelinkRequiredException) {
            return $this->searchError('relink', 'The music provider needs re-linking before search is available.', false);
        } catch (ProviderBackoffException) {
            return $this->searchError('busy', self::SEARCH_BUSY_MESSAGE);
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
        $found = $this->cachedProviderSearch(
            $party,
            $member,
            ['overview', $query, 1, self::SEARCH_OVERVIEW_LIMIT],
            fn (): SearchOverview => $provider->searchOverview($query, self::SEARCH_OVERVIEW_LIMIT),
        );
        if ($found === null) {
            return $this->searchError('busy', self::SEARCH_BUSY_MESSAGE);
        }

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
        $key = [$type->value, $query, $page, $perPage];
        $found = match ($type) {
            SearchType::Track => $this->cachedProviderSearch($party, $member, $key, fn () => $provider->searchTracks($query, $page, $perPage)),
            SearchType::Album => $this->cachedProviderSearch($party, $member, $key, fn () => $provider->searchAlbums($query, $page, $perPage)),
            SearchType::Artist => $this->cachedProviderSearch($party, $member, $key, fn () => $provider->searchArtists($query, $page, $perPage)),
        };
        if ($found === null) {
            return $this->searchError('busy', self::SEARCH_BUSY_MESSAGE);
        }

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

    public function album(SearchRequest $request, Party $party, string $album, MusicProviderFactory $factory, TrackEligibility $eligibility, PartyHeaderProps $header): Response|\Symfony\Component\HttpFoundation\Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $query = $request->input('query');

        $props = [
            'party' => $header->for($party, $request->user()),
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

    public function artist(SearchRequest $request, Party $party, string $artist, MusicProviderFactory $factory, TrackEligibility $eligibility, PartyHeaderProps $header): Response|\Symfony\Component\HttpFoundation\Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $query = $request->input('query');

        $props = [
            'party' => $header->for($party, $request->user()),
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

    /**
     * Provider responses are shared between members for SEARCH_CACHE_SECONDS; a hit makes no provider call and draws nothing from the member's bucket. Null means the member is over their limit.
     *
     * @template T of object
     *
     * @param  list<string|int>  $keyParts
     * @param  Closure(): T  $fetch
     * @return T|null
     */
    protected function cachedProviderSearch(Party $party, PartyMember $member, array $keyParts, Closure $fetch): ?object
    {
        $key = 'search-response:'.sha1(json_encode([$party->getAttribute('provider_id'), $party->country, ...$keyParts], JSON_THROW_ON_ERROR));

        $cached = Cache::get($key);
        if (is_object($cached)) {
            /** @var T $cached */
            return $cached;
        }

        if (! $this->withinSearchLimit($party, $member)) {
            return null;
        }

        $found = $fetch();
        Cache::put($key, $found, self::SEARCH_CACHE_SECONDS);

        return $found;
    }

    protected function withinSearchLimit(Party $party, PartyMember $member): bool
    {
        return new LeakyBucket(
            "search:{$party->getKey()}:{$member->user_id}",
            (int) config('musicparty.search_burst'),
            (float) config('musicparty.search_leak_per_second'),
        )->attempt();
    }
}
