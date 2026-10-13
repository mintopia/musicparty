<?php

use App\Enums\Purpose;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;

beforeEach(function () {
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
});

function linkAccount(User $user, string $code, bool $needsRelink = false): void
{
    $provider = SocialProvider::where('code', $code)->first() ?? SocialProvider::factory()->create(['code' => $code]);
    LinkedAccount::factory()->create([
        'user_id' => $user->id,
        'social_provider_id' => $provider->id,
        'needs_relink_at' => $needsRelink ? now() : null,
    ]);
}

function partyFor(User $user, string $providerId): Party
{
    return Party::factory()->create(['user_id' => $user->id, 'provider_id' => $providerId]);
}

it('reports a Spotify Party as needing relink only when the control account is flagged', function () {
    $user = User::factory()->create();
    $factory = app(MusicProviderFactory::class);
    $party = partyFor($user, 'spotify');

    expect($factory->partyNeedsRelink($party))->toBeFalse();

    linkAccount($user, 'spotify', needsRelink: true);

    expect($factory->partyNeedsRelink($party->fresh()))->toBeTrue();
});

it('never blocks a Fake Party on Spotify link state', function () {
    $user = User::factory()->create();
    linkAccount($user, 'spotify', needsRelink: true);
    $factory = app(MusicProviderFactory::class);

    expect($factory->partyNeedsRelink(partyFor($user, 'fake')))->toBeFalse()
        ->and($factory->forUser('fake', $user, Purpose::Control)->isLinked())->toBeTrue();
});

it('needs both Spotify accounts before Spotify can act for the user', function () {
    $user = User::factory()->create();
    $factory = app(MusicProviderFactory::class);

    expect($factory->forUser('spotify', $user, Purpose::Control)->isLinked())->toBeFalse();

    linkAccount($user, 'spotify');
    expect($factory->forUser('spotify', $user, Purpose::Control)->isLinked())->toBeFalse();

    linkAccount($user, 'spotifysearch');
    expect($factory->forUser('spotify', $user, Purpose::Control)->isLinked())->toBeTrue();
});
