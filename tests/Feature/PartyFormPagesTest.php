<?php

use App\Enums\Purpose;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\Role;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->app['env'] = 'local';
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new FakeMusicProvider);
});

function partyFormAdmin(): User
{
    $user = User::factory()->withSpotify()->create(['first_login' => false, 'market' => 'NZ']);
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    $search = Model::unguarded(fn () => SocialProvider::factory()->create(['code' => 'spotifysearch']));
    LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => $search->id]);

    return $user;
}

it('renders the create page as an Inertia page with form defaults', function () {
    $this->actingAs(partyFormAdmin())->get(route('parties.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Create', false)
            ->where('defaultCountry', 'NZ')
            ->where('party.allow_requests', true)
            ->where('party.name', null)
            ->has('providerIds')
            ->has('timezones')
            ->has('instanceTimezone'));
});

it('redirects create to the Spotify link page when the provider is not linked', function () {
    $user = User::factory()->create(['first_login' => false]);
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'create-party', 'name' => 'Create Party']));
    app(MusicProviderFactory::class)->register('spotify', fn () => new class extends FakeMusicProvider
    {
        public function isLinked(): bool
        {
            return false;
        }
    });

    $this->actingAs($user)->get(route('parties.create'))->assertRedirect(route('user.spotify'));
});

it('forbids create without the create-party role', function () {
    $this->actingAs(User::factory()->withSpotify()->create(['first_login' => false]))
        ->get(route('parties.create'))->assertForbidden();
});

it('renders the edit page with the party settings and hides the secret from managers', function () {
    config(['reverb.apps.apps.0.secret' => 'relay-secret']);
    $manager = User::factory()->withSpotify()->create(['first_login' => false]);
    $party = Party::factory()->for($manager)->create(['name' => 'Disco', 'country' => 'FR', 'max_requests' => 4]);

    $this->actingAs($manager)->get(route('parties.edit', $party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Edit', false)
            ->where('party.code', $party->code)
            ->where('party.name', 'Disco')
            ->where('party.country', 'FR')
            ->where('party.max_requests', 4)
            ->where('encore.secret', null)
            ->has('playerState'));
});

it('forbids editing a party for users who cannot manage it', function () {
    $party = Party::factory()->create();

    $this->actingAs(User::factory()->withSpotify()->create(['first_login' => false]))
        ->get(route('parties.edit', $party->code))->assertForbidden();
});

it('returns validation errors to the form and saves valid settings with checkbox booleans', function () {
    $admin = partyFormAdmin();
    $party = Party::factory()->for($admin)->create(['provider_id' => 'spotify']);

    $this->actingAs($admin)->from(route('parties.edit', $party->code))
        ->patch(route('parties.update', $party->code), ['name' => '', 'backup_playlist_id' => 'other', 'custom_backup_playlist_id' => 'abc'])
        ->assertRedirect(route('parties.edit', $party->code))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)->patch(route('parties.update', $party->code), [
        'name' => 'Renamed',
        'backup_playlist_id' => 'other',
        'custom_backup_playlist_id' => 'abc',
        'active' => true,
        'explicit' => false,
        'max_requests' => '3',
    ])->assertRedirect(route('parties.edit', $party->code));

    expect($party->fresh())
        ->name->toBe('Renamed')
        ->active->toBeTrue()
        ->explicit->toBeFalse()
        ->max_requests->toBe(3);
});

it('stores a fallback playlist chosen from the picker or given as a link', function () {
    $admin = partyFormAdmin();
    $party = Party::factory()->for($admin)->create(['provider_id' => 'spotify', 'backup_playlist_id' => 'OLD']);

    $this->actingAs($admin)->patch(route('parties.update', $party->code), [
        'name' => $party->name,
        'backup_playlist_id' => 'fake-playlist',
    ])->assertRedirect(route('parties.edit', $party->code));
    expect($party->fresh()->backup_playlist_id)->toBe('fake-playlist');

    $this->actingAs($admin)->patch(route('parties.update', $party->code), [
        'name' => $party->name,
        'backup_playlist_id' => 'other',
        'custom_backup_playlist_id' => 'LINKED',
    ])->assertRedirect(route('parties.edit', $party->code));
    expect($party->fresh()->backup_playlist_id)->toBe('LINKED');
});

it('clears the length and repeat limits when they are blanked or zeroed', function () {
    $admin = partyFormAdmin();
    $party = Party::factory()->for($admin)->create([
        'provider_id' => 'spotify',
        'min_song_length' => 60,
        'max_song_length' => 600,
        'no_repeat_interval' => 30,
    ]);
    $base = ['name' => $party->name, 'backup_playlist_id' => 'fake-playlist'];

    $this->actingAs($admin)->patch(route('parties.update', $party->code), $base + [
        'min_song_length' => '',
        'max_song_length' => '0',
        'no_repeat_interval' => '',
    ])->assertRedirect();

    expect($party->fresh())
        ->min_song_length->toBeNull()
        ->max_song_length->toBeNull()
        ->no_repeat_interval->toBeNull();
});

it('leaves limits unchanged when their fields are omitted from an update', function () {
    $admin = partyFormAdmin();
    $party = Party::factory()->for($admin)->create([
        'provider_id' => 'spotify',
        'min_song_length' => 60,
        'max_song_length' => 600,
        'no_repeat_interval' => 30,
        'max_requests' => 4,
        'downvotes_per_hour' => 5,
    ]);

    $this->actingAs($admin)->patch(route('parties.update', $party->code), [
        'name' => 'Renamed',
        'backup_playlist_id' => 'fake-playlist',
    ])->assertRedirect();

    expect($party->fresh())
        ->min_song_length->toBe(60)
        ->max_song_length->toBe(600)
        ->no_repeat_interval->toBe(30)
        ->max_requests->toBe(4)
        ->downvotes_per_hour->toBe(5);
});

it('returns a validation error when a party code rename loses a unique race', function () {
    $admin = partyFormAdmin();
    $party = Party::factory()->for($admin)->create(['provider_id' => 'spotify']);
    Party::updating(function (): void {
        throw new UniqueConstraintViolationException('sqlite', 'update parties', [], new Exception('duplicate'));
    });

    $this->actingAs($admin)->from(route('parties.edit', $party->code))
        ->patch(route('parties.update', $party->code), [
            'name' => $party->name,
            'backup_playlist_id' => 'fake-playlist',
            'code' => 'NEWCODE',
        ])
        ->assertRedirect(route('parties.edit', $party->code))
        ->assertSessionHasErrors('code');
});

it('answers 405 only for the legacy GET paths and 404 for other unknown GETs', function () {
    $this->get('/logout')->assertStatus(405);
    $this->get('/admin/users/1/impersonate')->assertStatus(405);
    $this->get('/nope/whatever')->assertNotFound();
});
