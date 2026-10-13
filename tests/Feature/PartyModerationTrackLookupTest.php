<?php

use App\Enums\Purpose;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Str;
use Tests\Support\FakeSpotify;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->withoutVite();
    $this->actingAs($this->owner);
});

function moderationParty(User $owner, string $providerId): Party
{
    $party = new Party;
    $party->forceFill(['code' => strtoupper(Str::random(4)), 'name' => 'Mod Party', 'user_id' => $owner->id, 'provider_id' => $providerId, 'active' => false, 'allow_requests' => true, 'explicit' => true]);
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

it('shows Spotify track details including ISRC through the Music Provider', function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $payload = FakeSpotify::track('abc', 'Spotify Song');
    $payload->external_ids = (object) ['isrc' => 'USRC17607839'];
    $provider = SocialProvider::query()->where('code', 'spotify')->first() ?? SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->create([
        'user_id' => $this->owner->id,
        'social_provider_id' => $provider->id,
        'access_token' => 'control-token',
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
    ]);
    FakeSpotify::install()->respond('getTrack', $payload);
    $party = moderationParty($this->owner, 'spotify');

    $this->get(route('parties.moderation.create', ['party' => $party->code, 'track' => 'abc']))
        ->assertOk()
        ->assertSee('Spotify Song')
        ->assertSee('USRC17607839')
        ->assertSee('Test Album')
        ->assertSee('Test Artist');
});

it('shows Fake provider track details with no ISRC', function () {
    $party = moderationParty($this->owner, 'fake');

    $this->get(route('parties.moderation.create', ['party' => $party->code, 'track' => 'fake-track-1']))
        ->assertOk()
        ->assertSee('Fake Track 1')
        ->assertSee('Fake Album 1')
        ->assertSee('Fake Artist 1')
        ->assertSee('ISRC Code');
});

it('renders the create page without track data when none is requested or found', function () {
    $party = moderationParty($this->owner, 'fake');

    $this->get(route('parties.moderation.create', $party->code))->assertOk()->assertDontSee('Track Data');
    $this->get(route('parties.moderation.create', ['party' => $party->code, 'track' => 'nope']))->assertOk()->assertDontSee('Track Data');
});
