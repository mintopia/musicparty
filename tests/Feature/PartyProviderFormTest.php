<?php

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\PartyUpdate;
use App\Jobs\RefreshFallbackPlaylist;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\Role;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\FallbackPlaylist;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\PlaybackSync;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->app['env'] = 'local';

    $this->spotify = new class extends FakeMusicProvider
    {
        public function listPlaylists(): array
        {
            return [new Playlist('sp-list', 'Spotify List', 'me', 1)];
        }

        public function devices(): array
        {
            return [new Device('sp-device', 'Spotify Speaker', 'Speaker', true)];
        }
    };
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => $this->spotify);

    $this->owner = User::factory()->withSpotify()->create();
    $this->owner->forceFill(['first_login' => false])->save();
    $search = Model::unguarded(fn () => SocialProvider::factory()->create(['code' => 'spotifysearch']));
    LinkedAccount::factory()->create(['user_id' => $this->owner->id, 'social_provider_id' => $search->id]);
    $this->owner->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
});

function payload(string $providerId, string $playlist): array
{
    return ['name' => 'Test', 'provider_id' => $providerId, 'backup_playlist_id' => $playlist];
}

it('offers the chosen provider\'s playlists and devices on create', function (string $query, string $playlist) {
    $this->actingAs($this->owner)->get(route('parties.create').$query)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Parties/Create', false)->where('playlists.0.name', $playlist)->has('playlists', 1));
})->with([
    'spotify' => ['', 'Spotify List'],
    'fake' => ['?provider_id=fake', 'Fake Playlist'],
]);

it('offers the party provider\'s playlists and devices on edit', function (string $providerId, string $device) {
    $party = Party::factory()->for($this->owner)->create(['provider_id' => $providerId]);

    $this->actingAs($this->owner)->get(route('parties.edit', $party->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Parties/Edit', false)->where('devices.0.name', $device)->has('devices', 1));
})->with([
    'spotify' => ['spotify', 'Spotify Speaker'],
    'fake' => ['fake', 'Fake Speaker'],
]);

it('names the party owner as the playlist host when an admin edits another user\'s party', function () {
    $other = User::factory()->withSpotify()->create();
    $party = Party::factory()->for($other)->create(['provider_id' => 'spotify']);

    $this->actingAs($this->owner)->get(route('parties.edit', $party->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hostName', $other->nickname));
});

it('validates the backup playlist against the chosen provider on create', function (string $providerId, string $valid, string $invalid) {
    $this->actingAs($this->owner)->post(route('parties.store'), payload($providerId, $invalid))
        ->assertSessionHasErrors('backup_playlist_id');
    expect(Party::count())->toBe(0);

    $this->actingAs($this->owner)->post(route('parties.store'), payload($providerId, $valid))
        ->assertSessionHasNoErrors();
    expect(Party::count())->toBe(1);
})->with([
    'spotify' => ['spotify', 'sp-list', 'fake-playlist'],
    'fake' => ['fake', 'fake-playlist', 'sp-list'],
]);

it('validates the backup playlist against the party provider on edit', function (string $providerId, string $valid, string $invalid) {
    $party = Party::factory()->for($this->owner)->create(['provider_id' => $providerId]);
    $body = ['name' => 'Test'];

    $this->actingAs($this->owner)->put(route('parties.update', $party->code), $body + ['backup_playlist_id' => $invalid])
        ->assertSessionHasErrors('backup_playlist_id');
    $this->actingAs($this->owner)->put(route('parties.update', $party->code), $body + ['backup_playlist_id' => $valid])
        ->assertSessionHasNoErrors();
    expect($party->fresh()->backup_playlist_id)->toBe($valid);
})->with([
    'spotify' => ['spotify', 'sp-list', 'fake-playlist'],
    'fake' => ['fake', 'fake-playlist', 'sp-list'],
]);

it('renders the form with empty lists when the provider needs a relink or is backing off', function (Throwable $exception) {
    $provider = new class($exception) extends FakeMusicProvider
    {
        public function __construct(private Throwable $exception)
        {
            parent::__construct();
        }

        public function listPlaylists(): array
        {
            throw $this->exception;
        }

        public function devices(): array
        {
            throw $this->exception;
        }
    };
    app(MusicProviderFactory::class)->register('fake', fn () => $provider);
    $party = Party::factory()->for($this->owner)->create(['provider_id' => 'fake']);

    $this->actingAs($this->owner)->get(route('parties.edit', $party->code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Parties/Edit', false)->where('playlists', [])->where('devices', []));
})->with([
    'relink' => [fn () => new class extends RuntimeException implements ProviderRelinkRequiredException {}],
    'backoff' => [fn () => new class extends RuntimeException implements ProviderBackoffException
    {
        public function retryAfterSeconds(): int
        {
            return 3;
        }
    }],
]);

it('releases the fallback refresh job on a provider-neutral backoff', function () {
    $party = Party::factory()->create();
    $exception = new class extends RuntimeException implements ProviderBackoffException
    {
        public function retryAfterSeconds(): int
        {
            return 7;
        }
    };
    $fallback = Mockery::mock(FallbackPlaylist::class);
    $fallback->shouldReceive('refresh')->andThrow($exception);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(7);
    $queueJob->shouldReceive('isReleased', 'isDeletedOrReleased')->andReturn(false);

    $job = new RefreshFallbackPlaylist($party);
    $job->setJob($queueJob);
    $job->handle($fallback);
});

it('swallows a provider-neutral relink in both party jobs', function () {
    $party = Party::factory()->create(['poll' => false]);
    $relink = new class extends RuntimeException implements ProviderRelinkRequiredException {};
    $fallback = Mockery::mock(FallbackPlaylist::class);
    $fallback->shouldReceive('refresh')->andThrow($relink);
    $sync = Mockery::mock(PlaybackSync::class);
    $sync->shouldReceive('sync')->andThrow($relink);
    app()->instance(PlaybackSync::class, $sync);

    $refresh = new RefreshFallbackPlaylist($party);
    $refresh->handle($fallback);
    $update = new PartyUpdate($party);
    $update->handle();

    $fallback->shouldHaveReceived('refresh')->once();
    $sync->shouldHaveReceived('sync')->once();
});

it('stores the Party country from the form and defaults it to the owner market', function () {
    $this->owner->forceFill(['market' => 'NZ'])->save();

    $this->actingAs($this->owner)->post(route('parties.store'), payload('spotify', 'sp-list') + ['country' => 'fr'])
        ->assertSessionHasNoErrors();
    expect(Party::query()->latest('id')->firstOrFail()->country)->toBe('FR');

    $this->actingAs($this->owner)->post(route('parties.store'), payload('spotify', 'sp-list'))
        ->assertSessionHasNoErrors();
    expect(Party::query()->latest('id')->firstOrFail()->country)->toBe('NZ');
});

it('rejects an invalid Party country', function () {
    $this->actingAs($this->owner)->post(route('parties.store'), payload('spotify', 'sp-list') + ['country' => 'GBR'])
        ->assertSessionHasErrors('country');
});

it('creates a party from the name alone using model defaults and flashes the created party', function () {
    $this->actingAs($this->owner)->post(route('parties.store'), ['name' => 'Quick'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('parties.create'))
        ->assertSessionMissing('successMessage');

    $party = Party::query()->where('name', 'Quick')->firstOrFail();
    expect($party->backup_playlist_id)->toBeNull()
        ->and($party->allow_requests)->toBeTrue()
        ->and($party->downvotes)->toBeTrue()
        ->and($party->explicit)->toBeTrue()
        ->and($party->active)->toBeTrue()
        ->and($party->show_qrcode)->toBeTrue();

    $created = session('createdParty');
    expect($created['code'])->toBe($party->code)
        ->and($created['name'])->toBe('Quick')
        ->and($created['joinUrl'])->toBe(route('parties.show', $party->code))
        ->and($created['qr'])->toContain('<svg');

    $this->actingAs($this->owner)->get(route('parties.create'))
        ->assertInertia(fn ($page) => $page->where('createdParty.code', $party->code)->where('party.downvotes', true));
    $this->actingAs($this->owner)->get(route('parties.create'))
        ->assertInertia(fn ($page) => $page->where('createdParty', null));
});

it('creates a party with every wizard field filled', function () {
    $this->actingAs($this->owner)->post(route('parties.store'), [
        'name' => 'Full',
        'backup_playlist_id' => 'sp-list',
        'history_playlist_id' => 'sp-list',
        'device_id' => 'sp-device',
        'allow_requests' => false,
        'downvotes' => false,
        'explicit' => false,
        'show_qrcode' => true,
        'flag_requests' => true,
        'max_requests' => 3,
        'downvotes_per_hour' => 5,
        'min_song_length' => 30,
        'max_song_length' => 600,
        'no_repeat_interval' => 60,
    ])->assertSessionHasNoErrors()->assertRedirect(route('parties.create'));

    $party = Party::query()->where('name', 'Full')->firstOrFail();
    expect($party->backup_playlist_id)->toBe('sp-list')
        ->and($party->history_playlist_id)->toBe('sp-list')
        ->and($party->device_id)->toBe('sp-device')
        ->and($party->device_name)->toBe('Spotify Speaker')
        ->and($party->allow_requests)->toBeFalse()
        ->and($party->downvotes)->toBeFalse()
        ->and($party->explicit)->toBeFalse()
        ->and($party->show_qrcode)->toBeTrue()
        ->and($party->flag_requests)->toBeTrue()
        ->and($party->max_requests)->toBe(3)
        ->and($party->downvotes_per_hour)->toBe(5)
        ->and($party->min_song_length)->toBe(30)
        ->and($party->max_song_length)->toBe(600)
        ->and($party->no_repeat_interval)->toBe(60);
});

it('rejects a missing name and a fallback playlist outside the host list with field-keyed errors', function () {
    $this->actingAs($this->owner)->post(route('parties.store'), ['backup_playlist_id' => 'nope'])
        ->assertSessionHasErrors(['name', 'backup_playlist_id']);
    expect(Party::count())->toBe(0);
});

it('sends cover, track count and owner for each playlist plus the host name on create', function () {
    $this->actingAs($this->owner)->get(route('parties.create'))
        ->assertInertia(fn ($page) => $page->where('playlists.0.trackCount', 1)->where('playlists.0.ownerName', 'me')->where('playlists.0.imageUrl', null)->where('hostName', $this->owner->nickname));
});

it('normalises a pasted playlist link or URI to the bare ID on create', function (string $pasted) {
    $this->actingAs($this->owner)->post(route('parties.store'), [
        'name' => 'Test', 'provider_id' => 'spotify', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => $pasted,
    ])->assertSessionDoesntHaveErrors();

    expect(Party::query()->latest('id')->firstOrFail()->backup_playlist_id)->toBe('37i9dQZF1DXcBWIGoYBM5M');
})->with([
    'url' => 'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M?si=abc123',
    'localised url' => 'https://open.spotify.com/intl-de/playlist/37i9dQZF1DXcBWIGoYBM5M',
    'uri' => 'spotify:playlist:37i9dQZF1DXcBWIGoYBM5M',
    'bare id' => ' 37i9dQZF1DXcBWIGoYBM5M ',
]);

it('rejects a pasted playlist value that is not an ID', function (string $pasted) {
    $this->actingAs($this->owner)->post(route('parties.store'), [
        'name' => 'Test', 'provider_id' => 'spotify', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => $pasted,
    ])->assertSessionHasErrors('custom_backup_playlist_id');
})->with(['empty' => '', 'junk' => 'not a playlist!', 'other site' => 'https://example.com/playlist/abc']);
