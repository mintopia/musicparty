<?php

use App\Domain\Admin\Actions\SaveProviderSetting;
use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Identity\Actions\CreateSocialProvider;
use App\Domain\Identity\Actions\SetSocialProviderEnabled;
use App\Domain\Identity\Actions\SetUserColourScheme;
use App\Domain\Identity\Actions\SetUserSuspended;
use App\Domain\Identity\Actions\UpdateLinkedAccount;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\ChangePartyPlayerSelection;
use App\Domain\Party\Actions\SelectPartyPlaylistIds;
use App\Domain\Party\Actions\StorePartyTheme;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\ClearUpNextEnqueued;
use App\Domain\Queue\Actions\MarkUpNextEnqueued;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Theming\ColourScheme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Enums\SettingType;

it('marks and clears the enqueued timestamp on a request', function () {
    $request = TrackRequest::factory()->create(['enqueued_at' => null]);

    app(MarkUpNextEnqueued::class)($request);
    expect($request->fresh()->enqueued_at)->not->toBeNull();

    app(ClearUpNextEnqueued::class)($request);
    expect($request->fresh()->enqueued_at)->toBeNull();
});

it('stores party player, playlists and theme attributes', function () {
    $party = Party::factory()->create();

    app(ChangePartyPlayerSelection::class)($party, 'browser', 'spotify');
    app(SelectPartyPlaylistIds::class)($party, 'fallback', null);
    app(StorePartyTheme::class)($party, ['tv_layout' => 'wide', 'theme' => ['font' => 'x']]);

    $fresh = $party->fresh();
    expect($fresh->player_kind)->toBe('browser')
        ->and($fresh->music_provider)->toBe('spotify')
        ->and($fresh->fallback_playlist_id)->toBe('fallback')
        ->and($fresh->history_playlist_id)->toBeNull()
        ->and($fresh->tv_layout)->toBe('wide')
        ->and($fresh->theme)->toBe(['font' => 'x']);
});

it('updates identity models', function () {
    $user = User::factory()->create();
    $account = LinkedAccount::factory()->create();
    $provider = $account->provider;

    app(SetUserSuspended::class)($user, true);
    app(SetUserColourScheme::class)($user, ColourScheme::Dark);
    app(UpdateLinkedAccount::class)($account, ['needs_relink' => true]);
    $provider->forceFill(['enabled' => false, 'auth_enabled' => false])->save();
    app(SetSocialProviderEnabled::class)($provider, true);

    expect($user->fresh()->suspended)->toBeTrue()
        ->and($user->fresh()->colour_scheme)->toBe(ColourScheme::Dark)
        ->and($account->fresh()->needs_relink)->toBeTrue()
        ->and($provider->fresh()->enabled)->toBeTrue()
        ->and($provider->fresh()->auth_enabled)->toBeTrue();
});

it('creates a social provider', function () {
    $provider = app(CreateSocialProvider::class)([
        'code' => 'demo', 'name' => 'Demo', 'provider_class' => 'X', 'supports_auth' => true,
        'enabled' => false, 'auth_enabled' => false, 'can_be_renamed' => false,
    ]);

    expect($provider->exists)->toBeTrue()->and(SocialProvider::query()->where('code', 'demo')->exists())->toBeTrue();
});

it('saves a provider setting only when it changed', function () {
    $provider = LinkedAccount::factory()->create()->provider;
    $setting = new ProviderSetting;
    $setting->provider()->associate($provider);
    $setting->forceFill(['code' => 'k', 'name' => 'K', 'type' => SettingType::stString, 'encrypted' => false, 'value' => 'a']);

    app(SaveProviderSetting::class)($setting);
    expect($setting->exists)->toBeTrue();

    $setting->value = 'b';
    app(SaveProviderSetting::class)($setting);
    expect($setting->fresh()?->value)->toBe('b');
});
