<?php

namespace App\Mods\Trust;

use App\Models\ModData;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use App\Services\ModDataStore;
use App\Services\ModSettingsStore;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrustScores
{
    public function __construct(
        protected ModDataStore $store,
        protected ModSettingsStore $settings,
        protected TrustCalculator $calculator,
    ) {}

    public function trustedUser(Party $party): ?User
    {
        $candidates = [
            $this->settings->value($party, TrustMod::CODE, 'trusted_user_id'),
            $this->store->get($party, TrustMod::CODE, 'trusted_user_id'),
        ];
        foreach ($candidates as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $user = User::find((int) $id);
            if ($user !== null) {
                return $user;
            }
        }

        return $party->user;
    }

    /**
     * @return array<int, float>
     */
    public function recalculate(Party $party, ?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $members = PartyMember::where('party_id', $party->id)->orderBy('id')->get(['id', 'user_id']);
        /** @var list<int> $memberIds */
        $memberIds = $members->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $trustedUser = $this->trustedUser($party);

        if ($trustedUser === null) {
            Log::debug("{$party} Trust: no trusted user, scores are zero");
            $scores = array_fill_keys($memberIds, 0.0);
        } else {
            $cutoff = $now->subDays((int) $this->settings->value($party, TrustMod::CODE, 'window_days', 7));
            $scores = $this->calculator->calculate($memberIds, $trustedUser->id, $this->endorsements($party, $cutoff));
            if (array_filter($scores) === []) {
                Log::debug("{$party} Trust: not enough signal, scores are zero");
            }
        }

        DB::transaction(function () use ($party, $members, $scores, $now): void {
            foreach ($members as $member) {
                $this->store->set($member, TrustMod::CODE, 'trustscore', $scores[$member->user_id] ?? 0.0);
            }
            $this->store->set($party, TrustMod::CODE, 'calculated_at', $now->toIso8601String());
        });

        return $scores;
    }

    public function recalculateIfDue(Party $party, ?CarbonInterface $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $calculatedAt = $this->store->get($party, TrustMod::CODE, 'calculated_at');
        $interval = (int) $this->settings->value($party, TrustMod::CODE, 'interval_minutes', 15);
        if (is_string($calculatedAt) && CarbonImmutable::parse($calculatedAt)->addMinutes($interval)->greaterThan($now)) {
            return;
        }

        $this->recalculate($party, $now);
    }

    public function scoreFor(Party $party, int $userId): float
    {
        return (float) $this->memberScores($party, $userId)->value('value');
    }

    public function forget(Party $party): void
    {
        DB::transaction(function () use ($party): void {
            $this->memberScores($party)->delete();
            $this->store->forget($party, TrustMod::CODE, 'calculated_at');
        });
    }

    /**
     * @return Builder<ModData>
     */
    protected function memberScores(Party $party, ?int $userId = null): Builder
    {
        return ModData::query()
            ->where('mod_code', TrustMod::CODE)
            ->where('owner_type', (new PartyMember)->getMorphClass())
            ->where('key', 'trustscore')
            ->whereIn('owner_id', PartyMember::query()
                ->select('id')
                ->where('party_id', $party->id)
                ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId)));
    }

    /**
     * @return array<int, array<int, int>>
     */
    protected function endorsements(Party $party, CarbonInterface $cutoff): array
    {
        $votes = DB::table('votes')
            ->join('upcoming_songs', 'upcoming_songs.id', '=', 'votes.upcoming_song_id')
            ->where('upcoming_songs.party_id', $party->id)
            ->whereNotNull('upcoming_songs.user_id')
            ->where('votes.value', '>', 0)
            ->where('votes.created_at', '>=', $cutoff)
            ->groupBy('votes.user_id', 'upcoming_songs.user_id')
            ->selectRaw('votes.user_id as voter, upcoming_songs.user_id as owner, count(*) as total')
            ->get();

        $ratings = DB::table('song_ratings')
            ->join('played_songs', 'played_songs.id', '=', 'song_ratings.played_song_id')
            ->join('upcoming_songs', 'upcoming_songs.id', '=', 'played_songs.upcoming_song_id')
            ->where('played_songs.party_id', $party->id)
            ->whereNotNull('upcoming_songs.user_id')
            ->where('song_ratings.value', '>', 0)
            ->where('song_ratings.created_at', '>=', $cutoff)
            ->groupBy('song_ratings.user_id', 'upcoming_songs.user_id')
            ->selectRaw('song_ratings.user_id as voter, upcoming_songs.user_id as owner, count(*) as total')
            ->get();

        $endorsements = [];
        foreach ($votes->concat($ratings) as $row) {
            $voter = (int) $row->voter;
            $owner = (int) $row->owner;
            $endorsements[$voter][$owner] = ($endorsements[$voter][$owner] ?? 0) + (int) $row->total;
        }

        return $endorsements;
    }
}
