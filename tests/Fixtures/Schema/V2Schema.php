<?php

namespace Tests\Fixtures\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The final v2 schema: everything the v1 and v2 migrations left in place.
 */
final class V2Schema
{
    /**
     * Tables that exist in v2 and not in v3.
     *
     * @var list<string>
     */
    public const V2_ONLY_TABLES = [
        'websockets_statistics_entries', 'themes', 'party_member_roles', 'artists', 'albums', 'songs',
        'artist_song', 'upcoming_songs', 'played_songs', 'votes', 'song_ratings', 'mods', 'mod_settings',
        'party_mod_settings', 'party_mod_setting_events', 'party_moderations', 'telescope_entries',
    ];

    public static function create(): void
    {
        self::createSharedTables();
        self::createPartyTables();
        self::createCatalogueTables();
        self::createModTables();
    }

    private static function createSharedTables(): void
    {
        Schema::create('telescope_entries', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->uuid('uuid')->unique();
            $table->string('type', 20);
            $table->longText('content');
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('websockets_statistics_entries', function (Blueprint $table) {
            $table->id();
            $table->string('app_id');
            $table->integer('peak_connection_count');
            $table->integer('websocket_message_count');
            $table->integer('api_message_count');
            $table->nullableTimestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nickname');
            $table->longText('avatar')->nullable();
            $table->timestamp('terms_agreed_at')->nullable();
            $table->boolean('first_login')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->boolean('suspended')->default(false);
            $table->string('status')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->string('market')->nullable();
            $table->timestamps();
        });

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

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('readonly')->default(false);
            $table->boolean('active')->default(false);
            $table->string('primary');
            $table->boolean('dark_mode')->default(false);
            $table->string('nav_background');
            $table->longText('css')->nullable();
            $table->timestamps();
        });
    }

    private static function createCatalogueTables(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('spotify_id');
            $table->timestamps();
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('spotify_id');
            $table->longText('image_url');
            $table->timestamps();
        });

        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id');
            $table->string('name');
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->integer('length');
            $table->timestamps();
        });

        Schema::create('artist_song', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    private static function createPartyTables(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('force')->default(false);
            $table->boolean('explicit')->default(true);
            $table->boolean('allow_requests')->default(true);
            $table->boolean('downvotes')->default(true);
            $table->integer('max_song_length')->nullable();
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('backup_playlist_id')->nullable();
            $table->string('backup_playlist_name')->nullable();
            $table->string('recent_device_id')->nullable();
            $table->string('history_playlist_id')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('song_started_at')->nullable();
            $table->unsignedBigInteger('song_id')->nullable();
            $table->integer('no_repeat_interval')->nullable();
            $table->boolean('poll')->default(true);
            $table->boolean('show_qrcode')->default(false);
            $table->integer('downvotes_per_hour')->nullable();
            $table->integer('min_song_length')->nullable();
            $table->boolean('weighted')->default(false);
            $table->boolean('trustscore')->default(false);
            $table->foreignId('trusted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('max_requests')->nullable();
            $table->timestamps();
        });

        Schema::create('party_member_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('party_member_roles')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->boolean('canvote')->default(true);
            $table->boolean('banned')->default(false);
            $table->float('trustscore')->default(0);
            $table->timestamps();
        });

        Schema::create('upcoming_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            $table->integer('score_adjustment')->default(0);
            $table->string('fallback_override')->nullable();
            $table->string('css_classes')->nullable();
            $table->timestamp('not_before')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('played_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upcoming_song_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('played_at');
            $table->integer('likes')->default(0);
            $table->integer('dislikes')->default(0);
            $table->integer('rating')->default(0);
            $table->string('relinked_from')->nullable();
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upcoming_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('value')->default(1);
            $table->timestamps();
        });

        Schema::create('song_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('played_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('value')->default(1);
            $table->timestamps();
        });
    }

    private static function createModTables(): void
    {
        Schema::create('mods', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->longText('description')->nullable();
            $table->timestamps();
        });

        Schema::create('mod_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('type');
            $table->boolean('encrypted')->default(false);
            $table->boolean('private')->default(false);
            $table->string('validation')->nullable();
            $table->longText('default')->nullable();
            $table->integer('order');
            $table->timestamps();
        });

        Schema::create('party_mod_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mod_setting_id')->constrained()->cascadeOnDelete();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('party_mod_setting_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('party_moderations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('mtName');
            $table->string('value');
            $table->string('notes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('regex')->default(false);
            $table->timestamps();
        });
    }
}
