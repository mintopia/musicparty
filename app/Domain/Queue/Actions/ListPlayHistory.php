<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\PlayHistorySort;
use App\Domain\Queue\PlayHistoryType;
use App\Domain\Queue\RequestStatus;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListPlayHistory
{
    public const int PER_PAGE = 20;

    public const array PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private const string SCORE_SQL = '(select coalesce(sum(request_votes.value), 0) from request_votes where request_votes.track_request_id = track_requests.id)';

    /**
     * @param  array{name?: string|null, artist?: string|null, album?: string|null, type?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return LengthAwarePaginator<int, Play|TrackRequest>
     */
    public function __invoke(Party $party, ?PartyMember $viewer = null, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $type = PlayHistoryType::tryFrom((string) ($filters['type'] ?? '')) ?? PlayHistoryType::All;
        $sort = PlayHistorySort::tryFrom((string) ($filters['sort'] ?? '')) ?? PlayHistorySort::Played;
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';

        $plays = $this->plays($party, $filters);
        $unsent = $this->unsent($party, $filters);
        $entries = match ($type) {
            PlayHistoryType::Played => $plays,
            PlayHistoryType::Unsent => $unsent,
            PlayHistoryType::All => $plays->unionAll($unsent),
        };

        $page = DB::query()
            ->fromSub($entries, 'history')
            ->orderBy($sort->column(), $direction)
            ->orderBy('played_at')
            ->orderBy('kind')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $rows = $page->getCollection();
        $playIds = $rows->where('kind', 'play')->pluck('id')->all();
        $requestIds = $rows->where('kind', 'unsent')->pluck('id')->all();

        $loadedPlays = $playIds === [] ? collect() : Play::query()
            ->whereIn('id', $playIds)
            ->withHistoryRelations()
            ->withRatingSummary($viewer)
            ->get()
            ->keyBy('id');
        $loadedRequests = $requestIds === [] ? collect() : TrackRequest::query()
            ->whereIn('id', $requestIds)
            ->with(['requester.user', 'party'])
            ->withCount('votes')
            ->withSum('votes as score', 'value')
            ->get()
            ->keyBy('id');

        return $page->setCollection($rows->map(
            fn (object $row): Play|TrackRequest|null => $row->kind === 'play' ? $loadedPlays->get($row->id) : $loadedRequests->get($row->id),
        )->filter()->values());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function plays(Party $party, array $filters): BaseBuilder
    {
        return Play::query()
            ->toBase()
            ->from('plays')
            ->leftJoin('track_requests', 'track_requests.id', '=', 'plays.track_request_id')
            ->where('plays.party_id', $party->id)
            ->tap(fn (QueryBuilder $query) => $this->filter($query, $filters, 'plays'))
            ->selectRaw("'play' as kind")
            ->addSelect('plays.id', 'plays.title', 'plays.artists', 'plays.album')
            ->selectRaw('coalesce(track_requests.created_at, plays.played_at) as requested_at')
            ->addSelect('plays.played_at')
            ->selectRaw('coalesce('.self::SCORE_SQL.', 0) as score');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function unsent(Party $party, array $filters): BaseBuilder
    {
        return TrackRequest::query()
            ->toBase()
            ->from('track_requests')
            ->where('track_requests.party_id', $party->id)
            ->whereIn('track_requests.status', [RequestStatus::Queued->value, RequestStatus::UpNext->value, RequestStatus::Removed->value])
            ->whereNotExists(fn (BaseBuilder $plays) => $plays->from('plays')->whereColumn('plays.track_request_id', 'track_requests.id'))
            ->tap(fn (QueryBuilder $query) => $this->filter($query, $filters, 'track_requests'))
            ->selectRaw("'unsent' as kind")
            ->addSelect('track_requests.id', 'track_requests.title', 'track_requests.artists', 'track_requests.album', 'track_requests.created_at as requested_at')
            ->selectRaw('null as played_at')
            ->selectRaw(self::SCORE_SQL.' as score');
    }

    /**
     * @param  BaseBuilder  $query
     * @param  array<string, mixed>  $filters
     */
    private function filter(QueryBuilder $query, array $filters, string $table): void
    {
        $contains = fn (?string $term, bool $jsonEncoded = false): ?string => $term === null || trim($term) === ''
            ? null
            : '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $jsonEncoded ? trim((string) json_encode(trim($term)), '"') : trim($term)).'%';

        if (($name = $contains($filters['name'] ?? null)) !== null) {
            $query->whereRaw(new Expression("{$table}.title like ? escape '!'"), [$name]);
        }

        if (($album = $contains($filters['album'] ?? null)) !== null) {
            $query->whereRaw(new Expression("{$table}.album like ? escape '!'"), [$album]);
        }

        if (($artist = $contains($filters['artist'] ?? null, true)) !== null) {
            $query->whereRaw(new Expression("LOWER({$table}.artists) like ? escape '!'"), [mb_strtolower($artist)]);
        }
    }
}
