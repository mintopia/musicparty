<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\PlayHistoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPlayHistory
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{name?: string|null, artist?: string|null, album?: string|null, type?: string|null}  $filters
     * @return LengthAwarePaginator<int, Play>
     */
    public function __invoke(Party $party, ?PartyMember $viewer = null, array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return Play::query()
            ->where('party_id', $party->id)
            ->tap(fn (Builder $query) => $this->filter($query, $filters))
            ->withHistoryRelations()
            ->withRatingSummary($viewer)
            ->orderBy('played_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Play>  $query
     * @param  array{name?: string|null, artist?: string|null, album?: string|null, type?: string|null}  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $contains = fn (?string $term, bool $jsonEncoded = false): ?string => $term === null || trim($term) === ''
            ? null
            : '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $jsonEncoded ? trim((string) json_encode(trim($term)), '"') : trim($term)).'%';

        if (($name = $contains($filters['name'] ?? null)) !== null) {
            $query->whereRaw("title like ? escape '!'", [$name]);
        }

        if (($album = $contains($filters['album'] ?? null)) !== null) {
            $query->whereRaw("album like ? escape '!'", [$album]);
        }

        if (($artist = $contains($filters['artist'] ?? null, true)) !== null) {
            $query->whereRaw("LOWER(artists) like ? escape '!'", [mb_strtolower($artist)]);
        }

        match (PlayHistoryType::tryFrom((string) ($filters['type'] ?? ''))) {
            PlayHistoryType::Requested => $query->whereNotNull('track_request_id'),
            PlayHistoryType::Fallback => $query->whereNull('track_request_id'),
            default => null,
        };
    }
}
