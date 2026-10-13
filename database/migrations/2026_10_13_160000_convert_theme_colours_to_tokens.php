<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Cache::forget('theme.active');
        if (! Schema::hasColumn('themes', 'tokens')) {
            Schema::table('themes', fn (Blueprint $table) => $table->json('tokens')->nullable()->after('active'));
        }

        if (Schema::hasColumn('themes', 'primary')) {
            DB::table('themes')->orderBy('id')->each(function (object $theme): void {
                DB::table('themes')->where('id', $theme->id)->update([
                    'tokens' => json_encode([
                        'primary' => $theme->primary,
                        'nav-background' => $theme->nav_background,
                    ]),
                ]);
            });

            Schema::table('themes', fn (Blueprint $table) => $table->dropColumn(['primary', 'nav_background']));
        }
    }

    public function down(): void
    {
        Cache::forget('theme.active');
        if (! Schema::hasColumn('themes', 'primary')) {
            Schema::table('themes', function (Blueprint $table): void {
                $table->string('primary')->default('#0054a6')->after('active');
                $table->string('nav_background')->default('#182433')->after('dark_mode');
            });
        }

        DB::table('themes')->orderBy('id')->each(function (object $theme): void {
            $tokens = json_decode((string) $theme->tokens, true) ?: [];
            DB::table('themes')->where('id', $theme->id)->update([
                'primary' => $tokens['primary'] ?? '#0054a6',
                'nav_background' => $tokens['nav-background'] ?? '#182433',
            ]);
        });

        Schema::table('themes', fn (Blueprint $table) => $table->dropColumn('tokens'));
    }
};
