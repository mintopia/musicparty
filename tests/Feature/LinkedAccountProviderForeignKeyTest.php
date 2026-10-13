<?php

use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function providerForeignKeyMigration(): object
{
    return require database_path('migrations/2026_10_13_110000_add_social_provider_foreign_key_to_linked_accounts.php');
}

function hasProviderForeignKey(): bool
{
    return collect(Schema::getForeignKeys('linked_accounts'))
        ->contains(fn (array $foreignKey) => $foreignKey['columns'] === ['social_provider_id']);
}

it('has the social provider foreign key after migrating', function () {
    expect(hasProviderForeignKey())->toBeTrue();
});

it('deletes linked accounts when their provider is deleted', function () {
    $provider = SocialProvider::forceCreate(['name' => 'Test', 'code' => 'test', 'provider_class' => 'x']);
    $account = LinkedAccount::factory()->create(['social_provider_id' => $provider->id]);

    $provider->delete();

    expect(LinkedAccount::find($account->id))->toBeNull();
});

it('nulls orphaned provider ids before adding the foreign key and is safe to repeat', function () {
    $migration = providerForeignKeyMigration();
    $migration->down();
    $user = User::factory()->create();
    $orphan = LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => 9999]);
    $valid = LinkedAccount::factory()->create(['user_id' => $user->id]);
    $validProviderId = $valid->social_provider_id;

    $migration->up();
    $migration->up();

    expect(hasProviderForeignKey())->toBeTrue()
        ->and(DB::table('linked_accounts')->where('id', $orphan->id)->value('social_provider_id'))->toBeNull()
        ->and(DB::table('linked_accounts')->where('id', $valid->id)->value('social_provider_id'))->toBe($validProviderId);
});
