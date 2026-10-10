<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRE_SQUASH_MARKER = '2026_10_10_020002_delete_spotifysearch_social_provider';

    /**
     * Tables created by the v3 baseline, children before parents.
     *
     * @var list<string>
     */
    private const V3_TABLES = [
        'party_stats',
        'party_mods',
        'integration_tokens',
        'instance_themes',
        'admin_host_sessions',
        'admin_audit_entries',
        'ratings',
        'plays',
        'blocklist_entries',
        'request_votes',
        'track_requests',
        'party_log_entries',
        'party_members',
        'parties',
        'provider_settings',
        'settings',
        'role_user',
        'roles',
        'linked_accounts',
        'social_providers',
        'personal_access_tokens',
        'failed_jobs',
        'users',
    ];

    /**
     * Tables only v1, v2 or an earlier v3 build created, children before parents.
     *
     * @var list<string>
     */
    private const LEGACY_TABLES = [
        'play_ratings',
        'party_logs',
        'party_moderations',
        'party_mod_setting_events',
        'party_mod_settings',
        'mod_settings',
        'mods',
        'song_ratings',
        'votes',
        'played_songs',
        'upcoming_songs',
        'artist_song',
        'songs',
        'albums',
        'artists',
        'themes',
        'party_member_roles',
        'websockets_statistics_entries',
        'telescope_entries_tags',
        'telescope_entries',
        'telescope_monitoring',
    ];

    public function up(): void
    {
        if ($this->isPreSquashV3()) {
            return;
        }

        $this->dropTables([...self::LEGACY_TABLES, ...self::V3_TABLES]);

        $this->createIdentityTables();
        $this->createPartyTables();
        $this->createQueueTables();
        $this->createAdminTables();
        $this->createFrameworkTables();
    }

    public function down(): void
    {
        $this->dropTables(self::V3_TABLES);
    }

    private function isPreSquashV3(): bool
    {
        return Schema::hasTable('migrations')
            && DB::table('migrations')->where('migration', self::PRE_SQUASH_MARKER)->exists()
            && Schema::hasTable('parties');
    }

    /**
     * @param  list<string>  $tables
     */
    private function dropTables(array $tables): void
    {
        $constraintsWereEnabled = $this->foreignKeyConstraintsEnabled();

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        if ($constraintsWereEnabled) {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function foreignKeyConstraintsEnabled(): bool
    {
        $query = DB::connection()->getDriverName() === 'sqlite'
            ? 'PRAGMA foreign_keys'
            : 'SELECT @@foreign_key_checks';

        return (bool) DB::scalar($query);
    }

    private function createIdentityTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nickname');
            $table->longText('avatar')->nullable();
            $table->timestamp('terms_agreed_at')->nullable();
            $table->boolean('first_login')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->boolean('suspended')->default(false);
            $table->timestamps();
            $table->string('colour_scheme', 10)->default('system');
        });

        Schema::create('social_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider_class');
            $table->boolean('supports_auth')->default(false);
            $table->boolean('enabled')->default(false);
            $table->boolean('auth_enabled')->default(false);
            $table->boolean('can_be_renamed')->default(false);
            $table->timestamps();
        });

        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('social_provider_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->longText('avatar_url')->nullable();
            $table->longText('email')->nullable();
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->boolean('needs_relink')->default(false);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private function createPartyTables(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('explicit')->default(true);
            $table->boolean('allow_requests')->default(true);
            $table->boolean('downvotes')->default(true);
            $table->integer('max_song_length')->nullable();
            $table->integer('no_repeat_interval')->nullable();
            $table->integer('downvotes_per_hour')->nullable();
            $table->integer('min_song_length')->nullable();
            $table->string('history_playlist_id')->nullable();
            $table->integer('max_requests')->nullable();
            $table->string('music_provider', 32)->nullable();
            $table->string('player_kind', 32)->nullable();
            $table->string('state', 16)->default('paused');
            $table->string('fallback_playlist_id')->nullable();
            $table->boolean('hold_requests')->default(false);
            $table->string('selection_mode', 16)->default('deterministic');
            $table->json('theme')->nullable();
            $table->string('theme_logo_path')->nullable();
            $table->string('theme_background_path')->nullable();
            $table->string('tv_layout')->default('default');
            $table->timestamps();
        });

        Schema::create('party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('guest');
            $table->boolean('canvote')->default(true);
            $table->boolean('banned')->default(false);
            $table->timestamps();

            $table->unique(['party_id', 'user_id']);
        });

        Schema::create('party_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('system_actor')->nullable();
            $table->string('action');
            $table->string('subject')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['party_id', 'id']);
        });

        Schema::create('blocklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('match_type');
            $table->string('value', 500);
            $table->boolean('is_regex')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'is_enabled']);
        });

        Schema::create('party_mods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('mod_id', 64);
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['party_id', 'mod_id']);
        });

        Schema::create('party_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();
        });
    }

    private function createQueueTables(): void
    {
        Schema::create('track_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider_track_id');
            $table->string('isrc', 12)->nullable();
            $table->string('title');
            $table->json('artists');
            $table->string('album')->nullable();
            $table->string('artwork_url', 2048)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('explicit')->default(false);
            $table->string('status')->default('queued');
            $table->foreignId('decided_by_member_id')->nullable()->constrained('party_members')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('rejection_reason', 200)->nullable();
            $table->timestamp('not_before')->nullable();
            $table->timestamp('up_next_at')->nullable();
            $table->timestamp('enqueued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->string('selection_mode', 16)->nullable();
            $table->integer('selection_score')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'status', 'provider_track_id']);
            $table->index(['party_id', 'isrc']);
            $table->index(['party_id', 'status']);
        });

        Schema::create('request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['track_request_id', 'party_member_id']);
        });

        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_track_id');
            $table->string('title');
            $table->json('artists');
            $table->string('album')->nullable();
            $table->string('artwork_url', 2048)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->boolean('explicit')->default(false);
            $table->string('selection_mode', 16)->nullable();
            $table->integer('selection_score')->nullable();
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['party_id', 'played_at']);
            $table->index(['party_id', 'provider_track_id']);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_member_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['play_id', 'party_member_id']);
        });
    }

    private function createAdminTables(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->boolean('hidden')->default(false);
            $table->longText('value')->nullable();
            $table->string('validation')->nullable();
            $table->string('type')->default('stString');
            $table->integer('order');
            $table->timestamps();
        });

        Schema::create('provider_settings', function (Blueprint $table) {
            $table->id();
            $table->morphs('provider');
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('type');
            $table->boolean('encrypted')->default(false);
            $table->string('validation')->nullable();
            $table->longText('value')->nullable();
            $table->integer('order');
            $table->timestamps();
        });

        Schema::create('admin_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_host_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'party_id']);
        });

        Schema::create('instance_themes', function (Blueprint $table) {
            $table->id();
            $table->json('tokens');
            $table->timestamps();
        });

        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private function createFrameworkTables(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
};
