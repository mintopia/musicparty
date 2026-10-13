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

it('offers the chosen provider\'s playlists and devices on create', function (string $query, string $see, string $dontSee) {
    $this->actingAs($this->owner)->get(route('parties.create').$query)
        ->assertOk()
        ->assertSee($see, false)
        ->assertDontSee($dontSee, false);
})->with([
    'spotify' => ['', 'Spotify List', 'Fake Playlist'],
    'fake' => ['?provider_id=fake', 'Fake Playlist', 'Spotify List'],
]);

it('offers the party provider\'s playlists and devices on edit', function (string $providerId, string $see, string $dontSee) {
    $party = Party::factory()->for($this->owner)->create(['provider_id' => $providerId]);

    $this->actingAs($this->owner)->get(route('parties.edit', $party->code))
        ->assertOk()
        ->assertSee($see, false)
        ->assertDontSee($dontSee, false);
})->with([
    'spotify' => ['spotify', 'Spotify Speaker', 'Fake Speaker'],
    'fake' => ['fake', 'Fake Speaker', 'Spotify Speaker'],
]);

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
        ->assertDontSee('Fake Playlist', false);
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
