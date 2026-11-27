<?php

namespace App\Http\Controllers;

use App\Enums\AiReviewOutcome;
use App\Enums\Purpose;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\UpcomingSongSearchRequest;
use App\Http\Requests\UpdateUpcomingSongScheduleRequest;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use App\Support\SearchTokens;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpcomingSongController extends Controller
{
    public function index(UpcomingSongSearchRequest $request, Party $party, MusicProviderFactory $providers): Response
    {
        $providerName = $providers->forParty($party, Purpose::Control)->displayName();
        $viewer = $request->user();
        $query = $party->upcoming()->withCount('votes')->with(['user', 'song', 'song.album', 'song.artists']);
        $params = [
            'order' => 'created_at',
            'order_direction' => 'desc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        $search = $this->searchFor($request);
        $params['q'] = SearchTokens::serialize($search);
        $tokens = $search['tokens'];

        foreach (SearchTokens::words($search['text']) as $word) {
            $term = "%{$word}%";
            $query = $query->where(function ($query) use ($term) {
                $query->whereHas('song', fn ($song) => $song->where('name', 'LIKE', $term))
                    ->orWhereHas('song.album', fn ($album) => $album->where('name', 'LIKE', $term))
                    ->orWhereHas('song.artists', fn ($artist) => $artist->where('name', 'LIKE', $term))
                    ->orWhereHas('user', fn ($user) => $user->where('nickname', 'LIKE', $term));
            });
        }

        foreach ($tokens['name'] ?? [] as $value) {
            $query = $query->whereHas('song', fn ($song) => $song->where('name', 'LIKE', "%{$value}%"));
        }
        foreach ($tokens['album'] ?? [] as $value) {
            $query = $query->whereHas('song.album', fn ($album) => $album->where('name', 'LIKE', "%{$value}%"));
        }
        foreach ($tokens['artist'] ?? [] as $value) {
            $query = $query->whereHas('song.artists', fn ($artist) => $artist->where('name', 'LIKE', "%{$value}%"));
        }
        foreach ($tokens['by'] ?? [] as $value) {
            $query = strtolower($value) === 'me'
                ? $query->where('user_id', $viewer?->id)
                : $query->whereHas('user', fn ($user) => $user->where('nickname', 'LIKE', "%{$value}%"));
        }
        foreach (['sent' => 'queued_at', 'scheduled' => 'scheduled_for', 'flagged' => 'flagged_at'] as $key => $column) {
            $state = $this->truthy($tokens[$key][0] ?? null);
            if ($state !== null) {
                $query = $state ? $query->whereNotNull($column) : $query->whereNull($column);
            }
        }

        // Sorting
        if ($request->input('order')) {
            $params['order'] = $request->input('order');
        }
        if ($request->input('order_direction')) {
            $params['order_direction'] = $request->input('order_direction');
        }

        switch ($params['order']) {
            case 'id':
            case 'created_at':
            case 'queued_at':
            case 'score':
                $query = $query->orderBy($params['order'], $params['order_direction']);
                break;
            case 'votes':
                $query = $query->orderBy('votes_count', $params['order_direction']);
                break;
        }

        $songs = $query->paginate($perPage)->appends($params);

        $zone = app(PartyTimezone::class)->dateTimeZone($party);
        $songs->through(fn (UpcomingSong $song): array => [
            'id' => $song->id,
            'name' => $song->song->name,
            'artists' => $song->song->artists->pluck('name')->all(),
            'albumName' => $song->song->album->name,
            'imageUrl' => $song->song->album->image_url,
            'votes' => (int) $song->votes_count,
            'score' => $song->score,
            'createdAt' => $this->when($song->created_at),
            'queuedAt' => $this->when($song->queued_at),
            'scheduledLabel' => $song->scheduled_for?->copy()->setTimezone($zone)->format('H:i'),
            'flagged' => $song->flagged_at !== null,
            'requestedBy' => $song->user ? [
                'nickname' => $song->user->nickname,
                'isYou' => $song->user_id === $viewer?->id,
            ] : null,
            'url' => route('parties.songs.show', [$party->code, $song->id]),
            'removeUrl' => route('parties.songs.destroy', [$party->code, $song->id]),
        ]);

        $counts = $party->upcoming()->selectRaw(
            'count(*) as total, count(queued_at) as sent, count(scheduled_for) as scheduled, count(flagged_at) as flagged, coalesce(sum(user_id = ?), 0) as mine',
            [$viewer?->id],
        )->toBase()->first();

        return Inertia::render('Parties/Songs/Index', [
            'party' => $this->partyProps($party),
            'providerName' => $providerName,
            'filters' => $params,
            'counts' => [
                'total' => (int) ($counts->total ?? 0),
                'sent' => (int) ($counts->sent ?? 0),
                'waiting' => (int) ($counts->total ?? 0) - (int) ($counts->sent ?? 0),
                'scheduled' => (int) ($counts->scheduled ?? 0),
                'flagged' => (int) ($counts->flagged ?? 0),
                'mine' => (int) ($counts->mine ?? 0),
            ],
            'songs' => $songs,
            'playerState' => $party->getState(),
        ]);
    }

    /**
     * @return array{text: string, tokens: array<string, list<string>>}
     */
    private function searchFor(UpcomingSongSearchRequest $request): array
    {
        $search = SearchTokens::parse((string) $request->input('q', ''), ['name', 'artist', 'album', 'by', 'sent', 'scheduled', 'flagged']);
        foreach (['name', 'artist', 'album'] as $key) {
            if ($request->filled($key)) {
                $search['tokens'][$key][] = (string) $request->input($key);
            }
        }
        if ($request->filled('type')) {
            $search['tokens']['sent'][] = $request->input('type') === 'queued' ? 'no' : 'yes';
        }

        return $search;
    }

    private function truthy(?string $value): ?bool
    {
        return match (strtolower((string) $value)) {
            'yes', 'true', '1' => true,
            'no', 'false', '0' => false,
            default => null,
        };
    }

    public function show(PaginationRequest $request, Party $party, UpcomingSong $song, MusicProviderFactory $providers): Response
    {
        $provider = $providers->forParty($party, Purpose::Control);
        $params = [];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }
        $votes = $song->votes()->orderBy('created_at', 'asc')->with(['user', 'user.partyMembers'])->paginate($perPage)->appends($params);
        $other = $party->upcoming()->whereSongId($song->song_id)->where('id', '<>', $song->id)->orderBy('created_at', 'desc')->get();
        $review = $song->aiReview;
        $played = $song->played;

        $voteRows = fn ($row): array => [
            'nickname' => $row->user->nickname,
            'userUrl' => $this->memberUrl($party, $row->user),
            'value' => (int) $row->value,
            'createdAt' => $this->when($row->created_at),
        ];
        $requester = $song->user;

        return Inertia::render('Parties/Songs/Show', [
            'party' => $this->partyProps($party),
            'providerName' => $provider->displayName(),
            'trackUrl' => $provider->trackUrl($song->song->provider_track_id),
            'song' => [
                'id' => $song->id,
                'name' => $song->song->name,
                'imageUrl' => $song->song->album->image_url,
                'albumName' => $song->song->album->name,
                'albumUrl' => route('parties.songs.index', [$party->code, 'album' => $song->song->album->name]),
                'artists' => $song->song->artists->map(fn ($artist): array => [
                    'name' => $artist->name,
                    'url' => route('parties.songs.index', [$party->code, 'artist' => $artist->name]),
                ])->values()->all(),
                'duration' => format_ms($song->song->length),
                'votes' => $song->votes()->count(),
                'score' => $song->score,
                'createdAt' => $this->when($song->created_at),
                'queuedAt' => $this->when($song->queued_at),
                'scheduledFor' => $this->when($song->scheduled_for),
                'scheduledForInput' => $song->scheduled_for
                    ? $song->scheduled_for->copy()->setTimezone(app(PartyTimezone::class)->dateTimeZone($party))->format('Y-m-d\TH:i')
                    : '',
                'requestedBy' => $requester ? [
                    'nickname' => $requester->nickname,
                    'url' => $this->memberUrl($party, $requester),
                ] : null,
                'moderateUrl' => route('parties.moderation.create', ['party' => $party->code, 'track' => $song->song->provider_track_id]),
                'removeUrl' => route('parties.songs.destroy', [$party->code, $song->id]),
            ],
            'votes' => $votes->through($voteRows),
            'aiReview' => $review ? [
                'outcome' => $review->outcome->value,
                'outcomeLabel' => $review->outcome->label(),
                'reason' => $review->reason,
                'criterion' => $review->criterion?->label(),
                'url' => route('parties.ai-reviews.show', [$party->code, $review->id]),
            ] : null,
            'history' => $this->history($song, $review, $played, $other),
            'playerState' => $party->getState(),
        ]);
    }

    public function update(UpdateUpcomingSongScheduleRequest $request, Party $party, UpcomingSong $song): RedirectResponse
    {
        $scheduledFor = $request->filled('scheduled_for')
            ? Carbon::parse((string) $request->input('scheduled_for'), app(PartyTimezone::class)->dateTimeZone($party))->utc()
            : null;
        $song->forceFill(['scheduled_for' => $scheduledFor, 'not_before' => $scheduledFor])->save();

        return response()->redirectToRoute('parties.songs.show', [$party->code, $song->id])->with('successMessage', 'The song schedule has been updated');
    }

    public function destroy(Party $party, UpcomingSong $song): RedirectResponse
    {
        $song->delete();
        $party->pushUpdate();

        return response()->redirectToRoute('parties.songs.index', $party->code)->with('successMessage', 'The Request has been removed');
    }

    /**
     * @param  Collection<int, UpcomingSong>  $other
     * @return list<array{icon: string, text: string, at: array{iso: string, human: string}|null, url: string|null}>
     */
    private function history(UpcomingSong $song, ?AiReview $review, ?PlayedSong $played, Collection $other): array
    {
        $party = $song->party;
        $events = [['icon' => 'ti-music-plus', 'text' => ($song->user ? $song->user->nickname.' requested it' : 'Added as a fallback track'), 'moment' => $song->created_at, 'url' => null]];
        if ($review) {
            $events[] = ['icon' => 'ti-robot', 'text' => match ($review->outcome) {
                AiReviewOutcome::Allow => 'AI review allowed it',
                AiReviewOutcome::Flag => 'AI review flagged it for a moderator',
                AiReviewOutcome::Reject => 'AI review rejected it',
                AiReviewOutcome::Error => 'AI review could not run',
            }, 'moment' => $review->created_at, 'url' => route('parties.ai-reviews.show', [$party->code, $review->id])];
        }
        if ($song->scheduled_for) {
            $events[] = ['icon' => 'ti-clock', 'text' => 'Scheduled to be sent to the Player', 'moment' => $song->scheduled_for, 'url' => null];
        }
        if ($song->queued_at) {
            $events[] = ['icon' => 'ti-list-numbers', 'text' => 'Sent to the Player', 'moment' => $song->queued_at, 'url' => null];
        }
        if ($played) {
            $events[] = ['icon' => 'ti-player-play', 'text' => 'Played at this party', 'moment' => $played->played_at, 'url' => null];
        }
        foreach ($other as $row) {
            $events[] = ['icon' => 'ti-history', 'text' => 'Requested again', 'moment' => $row->created_at, 'url' => route('parties.songs.show', [$party->code, $row->id])];
        }
        usort($events, fn (array $a, array $b): int => $a['moment'] <=> $b['moment']);

        return array_map(fn (array $event): array => [
            'icon' => $event['icon'],
            'text' => $event['text'],
            'at' => $this->when($event['moment']),
            'url' => $event['url'],
        ], $events);
    }

    /**
     * @return array{code: string, name: string, downvotes: bool, canManage: bool, canModerate: bool, weighted: bool}
     */
    private function partyProps(Party $party): array
    {
        return [
            'code' => $party->code,
            'name' => $party->name,
            'downvotes' => (bool) $party->downvotes,
            'canManage' => request()->user()?->can('update', $party) ?? false,
            'canModerate' => true,
            'weighted' => (bool) $party->weighted,
        ];
    }

    /**
     * @return array{iso: string, human: string}|null
     */
    private function when(?CarbonInterface $moment): ?array
    {
        return $moment ? ['iso' => $moment->format('Y-m-d H:i:s'), 'human' => $moment->diffForHumans()] : null;
    }

    private function memberUrl(Party $party, User $user): ?string
    {
        $member = $user->getPartyMember($party);

        return $member instanceof PartyMember ? route('parties.users.show', [$party->code, $member]) : null;
    }
}
