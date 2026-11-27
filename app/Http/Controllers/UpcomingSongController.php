<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\UpcomingSongSearchRequest;
use App\Http\Requests\UpdateUpcomingSongScheduleRequest;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class UpcomingSongController extends Controller
{
    public function index(UpcomingSongSearchRequest $request, Party $party, MusicProviderFactory $providers): Response
    {
        $providerName = $providers->forParty($party, Purpose::Control)->displayName();
        $query = $party->upcoming()->withCount('votes')->with(['user', 'song', 'song.album', 'song.artists']);
        $params = [
            'order' => 'created_at',
            'order_direction' => 'desc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        if ($request->input('name')) {
            $params['name'] = $request->input('name');
            $query = $query->whereHas('song', function ($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['name']}%");
            });
        }

        if ($request->input('album')) {
            $params['album'] = $request->input('album');
            $query = $query->whereHas('song.album', function ($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['album']}%");
            });
        }

        if ($request->input('artist')) {
            $params['artist'] = $request->input('artist');
            $query = $query->whereHas('song.artists', function ($query) use ($params) {
                $query->where('name', 'LIKE', "%{$params['artist']}%");
            });
        }

        if ($request->input('type')) {
            $params['type'] = $request->input('type');
            if ($request->input('type') === 'queued') {
                $query = $query->whereNull('queued_at');
            } else {
                $query = $query->whereNotNull('queued_at');
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
            'url' => route('parties.songs.show', [$party->code, $song->id]),
        ]);

        return Inertia::render('Parties/Songs/Index', [
            'party' => $this->partyProps($party),
            'providerName' => $providerName,
            'types' => [
                ['code' => 'queued', 'name' => 'Queued'],
                ['code' => 'spotify', 'name' => "Sent to {$providerName}"],
            ],
            'filters' => $params,
            'songs' => $songs,
            'playerState' => $party->getState(),
        ]);
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
        $other = $party->upcoming()->whereSongId($song->song_id)->where('id', '<>', $song->id)->withCount('votes')->orderBy('created_at', 'desc')->get();
        if ($song->played) {
            $ratings = $song->played->ratings()->orderBy('created_at', 'asc')->with(['user', 'user.partyMembers'])->paginate($perPage)->appends($params);
        } else {
            $ratings = new LengthAwarePaginator([], 0, $perPage);
        }

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
            ],
            'votes' => $votes->through($voteRows),
            'other' => $other->map(fn (UpcomingSong $row): array => [
                'id' => $row->id,
                'url' => route('parties.songs.show', [$party->code, $row->id]),
                'votes' => (int) $row->votes_count,
                'score' => $row->score,
                'createdAt' => $this->when($row->created_at),
                'queuedAt' => $this->when($row->queued_at),
            ])->values()->all(),
            'ratings' => $ratings->through($voteRows),
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
