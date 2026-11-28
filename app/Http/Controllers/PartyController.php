<?php

namespace App\Http\Controllers;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Requests\PartyRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\ModSettingsStore;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyHeaderProps;
use App\Services\PartyListing;
use App\Services\PartyProviderOptions;
use App\Services\PartyTimezone;
use App\Services\Playback\ContextPlaylist;
use App\Services\PlaybackControl;
use App\Services\Queue\QueueListing;
use App\Services\UpcomingSongAugmentService;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    private const array SETTINGS_TABS = ['general', 'requests', 'playback', 'screens', 'mods'];

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
        $party->getMember($party->user);
        PartyUpdate::dispatch($party);

        $joinUrl = route('parties.show', $party->code);

        return response()->redirectToRoute('parties.create', ['provider_id' => $party->provider_id])->with('createdParty', [
            'code' => $party->code,
            'name' => $party->name,
            'joinUrl' => $joinUrl,
            'qr' => QrCode::svg($joinUrl),
        ]);
    }

    public function show(UpcomingSongAugmentService $augmentService, QueueListing $queue, Request $request, Party $party, PartyHeaderProps $headerProps): Response
    {
        $member = $party->getMember($this->resolveAuthenticatedUser($request));
        $listing = $queue->page($party, 0, 20);
        $upcomingSongs = $listing['items'];

        $augmentData = $augmentService->augmentCollection($upcomingSongs, $this->resolveAuthenticatedUser($request));
        $upcoming = $upcomingSongs->map(function (UpcomingSong $song) use ($request, $augmentData, $listing) {
            $resource = new UpcomingSongResource($song);
            $resource->augment($augmentData[$song->id] ?? null);
            $resource->queueState($listing['states'][$song->id] ?? null);

            return $resource->toArray($request);
        });

        $header = $headerProps->for($party, $request->user());
        $canManage = $header['canManage'];

        return Inertia::render('Parties/Show', [
            'party' => $header,
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
                'qrSvg' => QrCode::svg(route('parties.show', $party->code)),
            ],
            'state' => $party->getState(...),
        ]);
    }

    public function edit(Request $request, Party $party, PartyProviderOptions $options, ModSettingsStore $mods): Response
    {
        $reverbApp = config('reverb.apps.apps.0');
        $isAdmin = $request->user()?->can('admin') ?? false;

        return Inertia::render('Parties/Edit', $this->formProps($options, $party, $this->resolveAuthenticatedUser($request)) + [
            'party' => $this->formValues($party) + ['code' => $party->code],
            'canEditCode' => $isAdmin,
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
        if ($request->user()?->can('admin') && $request->filled('code')) {
            $party->code = $request->string('code')->toString();
        }
        $this->updateObject($party, $request);

        if ($party->wasChanged('code')) {
            return redirect()->route('parties.edit', $party->code)->with('successMessage', 'The party has been updated');
        }

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
        $party->min_song_length = $request->input('min_song_length') ?: null;
        $party->max_song_length = $request->input('max_song_length') ?: null;
        $party->no_repeat_interval = $request->input('no_repeat_interval') ?: null;
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
        if ($party->exists) {
            $party->save();
        } else {
            $party->saveWithGeneratedCode();
        }
    }
}
