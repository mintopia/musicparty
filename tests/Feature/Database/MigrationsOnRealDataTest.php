<?php

use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMemberRole;
use App\Models\SocialProvider;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

function realDataMigration(string $file): object
{
    return require database_path("migrations/{$file}");
}

function undoDataMigrations(): void
{
    realDataMigration('2026_11_28_120000_add_unique_index_to_linked_accounts.php')->down();
    realDataMigration('2026_11_28_110000_dedupe_party_members_and_drop_ban_columns.php')->down();
    realDataMigration('2026_11_28_100000_add_unique_index_to_parties_code.php')->down();
}

function restoreLegacySchemaWithDuplicatesAllowed(): void
{
    realDataMigration('2026_11_28_120000_add_unique_index_to_linked_accounts.php')->down();
    realDataMigration('2026_11_28_100000_add_unique_index_to_parties_code.php')->down();
    Schema::table('party_members', function (Blueprint $table) {
        $table->index('party_id', 'legacy_party_members_party_id');
        $table->index('user_id', 'legacy_party_members_user_id');
    });
    Schema::table('party_members', function (Blueprint $table) {
        $table->dropUnique(['party_id', 'user_id']);
        $table->boolean('canvote')->default(true);
        $table->boolean('banned')->default(false);
    });
}

function applyDataMigrations(): void
{
    realDataMigration('2026_11_28_100000_add_unique_index_to_parties_code.php')->up();
    realDataMigration('2026_11_28_110000_dedupe_party_members_and_drop_ban_columns.php')->up();
    realDataMigration('2026_11_28_120000_add_unique_index_to_linked_accounts.php')->up();
}

beforeEach(function () {
    if (! TestCase::usesMariaDb()) {
        $this->markTestSkipped('Data migrations run against seeded duplicates on MariaDB only');
    }
    $this->seed(PartyMemberRoleSeeder::class);
});

it('keeps a user banned when their duplicate Party memberships are merged', function () {
    restoreLegacySchemaWithDuplicatesAllowed();
    $party = Party::factory()->create();
    $user = User::factory()->create();
    $banned = PartyMemberRole::where('code', 'banned')->value('id');
    $plain = PartyMemberRole::where('code', 'user')->value('id');
    $row = ['party_id' => $party->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()];
    DB::table('party_members')->insert([$row + ['role_id' => $plain], $row + ['role_id' => $banned]]);

    applyDataMigrations();

    $members = DB::table('party_members')->where('party_id', $party->id)->where('user_id', $user->id)->get();
    expect($members)->toHaveCount(1)
        ->and($members->first()->role_id)->toBe($banned);
});

it('keeps one linked account per provider identity and leaves every other account alone', function () {
    restoreLegacySchemaWithDuplicatesAllowed();
    $provider = SocialProvider::forceCreate(['name' => 'Test', 'code' => 'test', 'provider_class' => 'x']);
    $first = LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'dup']);
    DB::table('linked_accounts')->insert(['user_id' => User::factory()->create()->id, 'social_provider_id' => $provider->id, 'external_id' => 'dup', 'created_at' => now(), 'updated_at' => now()]);
    $other = LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'solo']);

    applyDataMigrations();

    expect(DB::table('linked_accounts')->where('external_id', 'dup')->pluck('id')->all())->toBe([$first->id])
        ->and(LinkedAccount::find($other->id))->not->toBeNull();
});

it('renames duplicate Party codes, reports each rename and keeps the oldest Party on its code', function () {
    restoreLegacySchemaWithDuplicatesAllowed();
    $oldest = Party::factory()->create(['code' => 'ABCD']);
    $clone = collect(DB::table('parties')->find($oldest->id))->except('id')->all();
    $duplicateId = DB::table('parties')->insertGetId($clone);
    Log::spy();

    applyDataMigrations();

    $renamed = DB::table('parties')->where('id', $duplicateId)->value('code');
    expect(DB::table('parties')->where('id', $oldest->id)->value('code'))->toBe('ABCD')
        ->and($renamed)->not->toBe('ABCD')
        ->and(DB::table('parties')->distinct()->count('code'))->toBe(2);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['party_id'] === $duplicateId && $context['old_code'] === 'ABCD' && $context['new_code'] === $renamed)->once();
});

it('rolls every data migration back and forward again', function () {
    undoDataMigrations();
    applyDataMigrations();

    expect(Schema::hasColumn('party_members', 'banned'))->toBeFalse()
        ->and(Schema::hasColumn('party_members', 'canvote'))->toBeFalse();
});
