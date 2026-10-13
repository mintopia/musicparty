<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_data', function (Blueprint $table) {
            $table->id();
            $table->string('mod_code');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['mod_code', 'owner_type', 'owner_id', 'key']);
            $table->index(['owner_type', 'owner_id']);
        });

        $this->copyToModData('upcoming_songs', UpcomingSong::class, 'whamageddon', 'fallback_override', fn ($query) => $query->whereNotNull('fallback_override'));
        $this->copyToModData('upcoming_songs', UpcomingSong::class, 'whamageddon', 'css_classes', fn ($query) => $query->whereNotNull('css_classes'));
        $this->copyToModData('parties', Party::class, 'trust', 'trustscore', fn ($query) => $query->where('trustscore', true));
        $this->copyToModData('parties', Party::class, 'trust', 'trusted_user_id', fn ($query) => $query->whereNotNull('trusted_user_id'));
        $this->copyToModData('party_members', PartyMember::class, 'trust', 'trustscore', fn ($query) => $query->where('trustscore', '!=', 0));

        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->dropColumn(['fallback_override', 'css_classes']);
        });
        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeign(['trusted_user_id']);
            $table->dropColumn(['trustscore', 'trusted_user_id']);
        });
        Schema::table('party_members', function (Blueprint $table) {
            $table->dropColumn('trustscore');
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_songs', function (Blueprint $table) {
            $table->string('fallback_override')->nullable()->default(null)->after('score_adjustment');
            $table->string('css_classes')->nullable()->default(null)->after('fallback_override');
        });
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('trustscore')->default(false)->after('weighted');
            $table->unsignedBigInteger('trusted_user_id')->nullable()->default(null)->after('trustscore');
            $table->foreign('trusted_user_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('party_members', function (Blueprint $table) {
            $table->float('trustscore')->default(0)->after('banned');
        });

        $this->restoreFromModData('upcoming_songs', UpcomingSong::class, 'whamageddon', 'fallback_override');
        $this->restoreFromModData('upcoming_songs', UpcomingSong::class, 'whamageddon', 'css_classes');
        $this->restoreFromModData('parties', Party::class, 'trust', 'trustscore', fn ($value) => (bool) $value);
        $this->restoreFromModData('parties', Party::class, 'trust', 'trusted_user_id', onlyExistingUsers: true);
        $this->restoreFromModData('party_members', PartyMember::class, 'trust', 'trustscore');

        Schema::dropIfExists('mod_data');
    }

    /**
     * @param  class-string<Model>  $ownerClass
     */
    private function copyToModData(string $table, string $ownerClass, string $modCode, string $key, Closure $constraint): void
    {
        $ownerType = (new $ownerClass)->getMorphClass();
        $now = now();

        $constraint(DB::table($table))->select(['id', $key])->orderBy('id')->chunkById(500, function ($rows) use ($ownerType, $modCode, $key, $now): void {
            DB::table('mod_data')->insert($rows->map(fn ($row): array => [
                'mod_code' => $modCode,
                'owner_type' => $ownerType,
                'owner_id' => $row->id,
                'key' => $key,
                'value' => json_encode($row->{$key}),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    /**
     * @param  class-string<Model>  $ownerClass
     */
    private function restoreFromModData(string $table, string $ownerClass, string $modCode, string $key, ?Closure $cast = null, bool $onlyExistingUsers = false): void
    {
        $ownerType = (new $ownerClass)->getMorphClass();

        DB::table('mod_data')
            ->where('mod_code', $modCode)
            ->where('owner_type', $ownerType)
            ->where('key', $key)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $key, $cast, $onlyExistingUsers): void {
                foreach ($rows as $row) {
                    $value = json_decode((string) $row->value, true);
                    if ($onlyExistingUsers && ! DB::table('users')->where('id', $value)->exists()) {
                        continue;
                    }
                    DB::table($table)->where('id', $row->owner_id)->update([$key => $cast ? $cast($value) : $value]);
                }
            });
    }
};
