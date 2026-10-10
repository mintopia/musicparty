<?php

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('keeps the stored polymorphic aliases for moved models', function (string $alias, string $class) {
    expect(Relation::getMorphedModel($alias))->toBe($class)
        ->and((new $class)->getMorphClass())->toBe($alias);
})->with([
    'user' => ['App\\Models\\User', User::class],
    'party' => ['App\\Models\\Party', Party::class],
    'social provider' => ['App\\Models\\SocialProvider', SocialProvider::class],
]);

it('resolves a token stored with the legacy tokenable_type', function () {
    $user = User::factory()->create();
    $token = $user->createToken('legacy');

    expect(DB::table('personal_access_tokens')->value('tokenable_type'))->toBe('App\\Models\\User')
        ->and(PersonalAccessToken::findToken($token->plainTextToken)->tokenable->is($user))->toBeTrue();
});

it('resolves provider settings through the stored provider_type', function () {
    $provider = SocialProvider::factory()->create();
    DB::table('provider_settings')->insert([
        'provider_type' => 'App\\Models\\SocialProvider',
        'provider_id' => $provider->id,
        'name' => 'Client ID',
        'code' => 'client_id',
        'type' => 'string',
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $setting = ProviderSetting::query()->firstOrFail();

    expect($setting->provider->is($provider))->toBeTrue();
});

it('no longer has the moved classes in their old locations', function (string $class) {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Models\\User', 'App\\Models\\Party', 'App\\Models\\PartyMember', 'App\\Models\\Role',
    'App\\Models\\Setting', 'App\\Models\\SocialProvider', 'App\\Models\\IntegrationToken',
    'App\\Services\\SocialProviders\\DiscordProvider', 'App\\Domain\\Party\\PartyRole',
    'App\\Events\\Party\\PartyLogEntryAddedEvent',
]);
