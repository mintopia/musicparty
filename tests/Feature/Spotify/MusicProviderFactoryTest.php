<?php

use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

function factoryUser(bool $withSearchAccount): User
{
    $user = User::factory()->create();
    $codes = $withSearchAccount ? ['spotify', 'spotifysearch'] : ['spotify'];
    foreach ($codes as $code) {
        $provider = SocialProvider::factory()->create(['code' => $code]);
        foreach (['client_id', 'client_secret'] as $setting) {
            $row = new ProviderSetting;
            $row->forceFill(['code' => $setting, 'name' => $setting, 'type' => SettingType::stString, 'value' => 'test']);
            $row->provider()->associate($provider);
            $row->save();
        }
        LinkedAccount::factory()->create([
            'user_id' => $user->id,
            'social_provider_id' => $provider->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'access_token_expires_at' => now()->addHour(),
        ]);
    }

    return $user;
}

function recordingGateway(FakeSpotify $api): SpotifyGateway
{
    return new class($api) extends SpotifyGateway
    {
        /** @var list<string> */
        public array $used = [];

        public function __construct(protected FakeSpotify $api) {}

        public function client(User $user): ?SpotifyWebAPI
        {
            $this->used[] = 'control';

            return $this->api;
        }

        public function searchClient(User $user): SpotifyWebAPI
        {
            $this->used[] = 'search';

            return $this->api;
        }
    };
}

function spotifyFactory(SpotifyGateway $gateway): MusicProviderFactory
{
    return new MusicProviderFactory([
        'spotify' => fn (User $user, Purpose $purpose) => new SpotifyMusicProvider($gateway, $user, $purpose),
    ]);
}

it('binds the fake provider and factory registry in testing', function () {
    $party = Party::factory()->create();

    expect(app(MusicProvider::class))->toBeInstanceOf(FakeMusicProvider::class)
        ->and(app(MusicProviderFactory::class)->forParty($party, Purpose::Control))->toBe(app(FakeMusicProvider::class))
        ->and(app(MusicProviderFactory::class)->ids())->toBe(['spotify', 'fake']);
});

it('uses the Search account for the Search purpose', function () {
    $api = FakeSpotify::install();
    $party = Party::factory()->create(['user_id' => factoryUser(true)->id]);
    $gateway = recordingGateway($api);

    $provider = spotifyFactory($gateway)->forParty($party, Purpose::Search);
    $provider->searchTracks('hello', 1, 10);

    expect($provider)->toBeInstanceOf(SpotifyMusicProvider::class)
        ->and($gateway->used)->toBe(['search'])
        ->and($api->callsTo('search')[0][2])->toMatchArray(['limit' => 10, 'offset' => 0]);
});

it('uses the control account for the Control purpose', function () {
    $api = FakeSpotify::install();
    $party = Party::factory()->create(['user_id' => factoryUser(true)->id]);
    $gateway = recordingGateway($api);

    spotifyFactory($gateway)->forParty($party, Purpose::Control)->searchTracks('hello', 1, 10);

    expect($gateway->used)->toBe(['control']);
});

it('falls back to the control account when there is no Search account', function () {
    $api = FakeSpotify::install();
    $party = Party::factory()->create(['user_id' => factoryUser(false)->id]);

    $page = spotifyFactory(new SpotifyGateway)->forParty($party, Purpose::Search)->searchTracks('hello', 2, 10);

    expect($api->callsTo('search'))->toHaveCount(1)
        ->and($api->callsTo('search')[0][2]['offset'])->toBe(10)
        ->and($page->page)->toBe(2);
});

it('rejects an unregistered provider id', function () {
    $party = Party::factory()->create();
    $party->setAttribute('provider_id', 'tidal');

    spotifyFactory(new SpotifyGateway)->forParty($party, Purpose::Control);
})->throws(InvalidArgumentException::class);
