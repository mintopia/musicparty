<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */

namespace App\Domain\Admin\Models{
    use App\Domain\Identity\Models\User;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $admin_id
     * @property string $action
     * @property int|null $subject_user_id
     * @property array<array-key, mixed>|null $meta
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read User $admin
     * @property-read User|null $subject
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereAction($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereAdminId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereMeta($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereSubjectUserId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditEntry whereUpdatedAt($value)
     */
    class AdminAuditEntry extends \Eloquent {}
}

namespace App\Domain\Admin\Models{
    use App\Domain\Identity\Models\User;
    use App\Domain\Party\Models\Party;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $user_id
     * @property int $party_id
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Party $party
     * @property-read User $user
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminHostSession whereUserId($value)
     */
    class AdminHostSession extends \Eloquent {}
}

namespace App\Domain\Admin\Models{
    use App\Domain\Identity\Models\User;
    use Illuminate\Contracts\Auth\Authenticatable;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property string $name
     * @property string $token_hash
     * @property array<array-key, mixed> $abilities
     * @property Carbon|null $last_used_at
     * @property Carbon|null $revoked_at
     * @property int $created_by
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read User $creator
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereAbilities($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereCreatedBy($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereLastUsedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereRevokedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereTokenHash($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|IntegrationToken whereUpdatedAt($value)
     */
    class IntegrationToken extends \Eloquent implements Authenticatable {}
}

namespace App\Domain\Admin\Models{
    use App\Enums\SettingType;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Carbon;
    use Spatie\EloquentSortable\Sortable;

    /**
     * @mixin IdeHelperProviderSetting
     *
     * @property int $id
     * @property string $provider_type
     * @property int $provider_id
     * @property string $name
     * @property string $code
     * @property string|null $description
     * @property SettingType $type
     * @property int $encrypted
     * @property string|null $validation
     * @property mixed|null $value
     * @property int $order
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Model|\Eloquent $provider
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting ordered(string $direction = 'asc')
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereCode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereDescription($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereEncrypted($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereOrder($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereProviderId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereProviderType($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereType($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereValidation($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|ProviderSetting whereValue($value)
     */
    class ProviderSetting extends \Eloquent implements Sortable {}
}

namespace App\Domain\Admin\Models{
    use App\Domain\Identity\Models\User;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Support\Carbon;

    /**
     * @mixin IdeHelperRole
     *
     * @property int $id
     * @property string $code
     * @property string $name
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Collection<int, User> $users
     * @property-read int|null $users_count
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
     */
    class Role extends \Eloquent {}
}

namespace App\Domain\Admin\Models{
    use App\Enums\SettingType;
    use Illuminate\Support\Carbon;
    use Spatie\EloquentSortable\Sortable;

    /**
     * @mixin IdeHelperSetting
     *
     * @property int $id
     * @property string $code
     * @property string $name
     * @property string|null $description
     * @property int $encrypted
     * @property int $hidden
     * @property mixed|null $value
     * @property string|null $validation
     * @property SettingType $type
     * @property int $order
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting ordered(string $direction = 'asc')
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereEncrypted($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereHidden($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereOrder($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValidation($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
     */
    class Setting extends \Eloquent implements Sortable {}
}

namespace App\Domain\Identity\Models{
    use Illuminate\Support\Carbon;

    /**
     * @mixin IdeHelperLinkedAccount
     *
     * @property int $id
     * @property int $user_id
     * @property int|null $social_provider_id
     * @property string|null $external_id
     * @property string|null $name
     * @property string|null $avatar_url
     * @property string|null $email
     * @property string|null $access_token
     * @property string|null $refresh_token
     * @property Carbon|null $access_token_expires_at
     * @property bool $needs_relink
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read SocialProvider|null $provider
     * @property-read User $user
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAccessToken($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAccessTokenExpiresAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereAvatarUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereEmail($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereExternalId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereNeedsRelink($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereRefreshToken($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereSocialProviderId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUserId($value)
     */
    class LinkedAccount extends \Eloquent {}
}

namespace App\Domain\Identity\Models{
    use Illuminate\Support\Carbon;

    /**
     * @property-read Collection<int, ProviderSetting> $settings
     *
     * @mixin IdeHelperSocialProvider
     *
     * @property int $id
     * @property string $name
     * @property string $code
     * @property string $provider_class
     * @property int $supports_auth
     * @property bool $enabled
     * @property bool $auth_enabled
     * @property int $can_be_renamed
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read \Illuminate\Database\Eloquent\Collection<int, LinkedAccount> $accounts
     * @property-read int|null $accounts_count
     * @property-read int|null $settings_count
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereAuthEnabled($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCanBeRenamed($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereEnabled($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereProviderClass($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereSupportsAuth($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|SocialProvider whereUpdatedAt($value)
     */
    class SocialProvider extends \Eloquent {}
}

namespace App\Domain\Identity\Models{
    use App\Domain\Admin\Models\Role;
    use App\Domain\Membership\Models\PartyMember;
    use App\Domain\Party\Models\Party;
    use App\Domain\Theming\ColourScheme;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Notifications\DatabaseNotification;
    use Illuminate\Notifications\DatabaseNotificationCollection;
    use Illuminate\Support\Carbon;
    use Laravel\Sanctum\PersonalAccessToken;

    /**
     * @mixin IdeHelperUser
     *
     * @property int $id
     * @property string $nickname
     * @property string|null $avatar
     * @property Carbon|null $terms_agreed_at
     * @property bool $first_login
     * @property Carbon|null $last_login
     * @property bool $suspended
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property ColourScheme $colour_scheme
     * @property-read Collection<int, LinkedAccount> $accounts
     * @property-read int|null $accounts_count
     * @property-read Collection<int, Party> $memberParties
     * @property-read int|null $member_parties_count
     * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
     * @property-read int|null $notifications_count
     * @property-read Collection<int, Party> $parties
     * @property-read int|null $parties_count
     * @property-read Collection<int, PartyMember> $partyMembers
     * @property-read int|null $party_members_count
     * @property-read Collection<int, Role> $roles
     * @property-read int|null $roles_count
     * @property-read Collection<int, PersonalAccessToken> $tokens
     * @property-read int|null $tokens_count
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereColourScheme($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstLogin($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNickname($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuspended($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTermsAgreedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
     */
    class User extends \Eloquent {}
}

namespace App\Domain\Membership\Models{
    use App\Domain\Identity\Models\User;
    use App\Domain\Party\Models\Party;
    use Illuminate\Support\Carbon;

    /**
     * @property PartyRole $role
     * @property bool $banned
     *
     * @mixin IdeHelperPartyMember
     *
     * @property int $id
     * @property int $user_id
     * @property int $party_id
     * @property int $canvote
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Party $party
     * @property-read User $user
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereBanned($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCanvote($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereRole($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUserId($value)
     */
    class PartyMember extends \Eloquent {}
}

namespace App\Domain\Party\Models{
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $party_id
     * @property BlocklistMatchType $match_type
     * @property string $value
     * @property bool $is_regex
     * @property bool $is_enabled
     * @property string|null $notes
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Party $party
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereIsEnabled($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereIsRegex($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereMatchType($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereNotes($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|BlocklistEntry whereValue($value)
     */
    class BlocklistEntry extends \Eloquent {}
}

namespace App\Domain\Party\Models{
    use App\Domain\Admin\Models\AdminHostSession;
    use App\Domain\Identity\Models\User;
    use App\Domain\Membership\Models\PartyMember;
    use App\Domain\Queue\SelectionMode;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Support\Carbon;
    use Laravel\Sanctum\PersonalAccessToken;

    /**
     * @property PartyState $state
     * @property string $music_provider
     *
     * @mixin IdeHelperParty
     *
     * @property int $id
     * @property string $code
     * @property string $name
     * @property int $user_id
     * @property bool $explicit
     * @property bool $allow_requests
     * @property bool $downvotes
     * @property int|null $max_song_length
     * @property int|null $no_repeat_interval
     * @property int|null $downvotes_per_hour
     * @property int|null $min_song_length
     * @property string|null $history_playlist_id
     * @property int|null $max_requests
     * @property string|null $player_kind
     * @property string|null $fallback_playlist_id
     * @property bool $hold_requests
     * @property SelectionMode $selection_mode
     * @property array<array-key, mixed>|null $theme
     * @property string|null $theme_logo_path
     * @property string|null $theme_background_path
     * @property string $tv_layout
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Collection<int, AdminHostSession> $adminHostSessions
     * @property-read int|null $admin_host_sessions_count
     * @property-read Collection<int, BlocklistEntry> $blocklistEntries
     * @property-read int|null $blocklist_entries_count
     * @property-read Collection<int, PartyMember> $members
     * @property-read int|null $members_count
     * @property-read Collection<int, PersonalAccessToken> $tokens
     * @property-read int|null $tokens_count
     * @property-read User $user
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereAllowRequests($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotes($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotesPerHour($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereExplicit($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereFallbackPlaylistId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereHistoryPlaylistId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereHoldRequests($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMaxRequests($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMaxSongLength($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMinSongLength($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMusicProvider($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereName($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereNoRepeatInterval($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party wherePlayerKind($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereSelectionMode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereState($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTheme($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereThemeBackgroundPath($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereThemeLogoPath($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTvLayout($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUserId($value)
     */
    class Party extends \Eloquent {}
}

namespace App\Domain\Party\Models{
    /**
     * @property int $id
     * @property int $party_id
     * @property int|null $user_id
     * @property string|null $system_actor
     * @property string $action
     * @property string|null $subject
     * @property array<string, mixed>|null $details
     * @property Carbon $created_at
     * @property-read User|null $user
     * @property-read Party $party
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereAction($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereDetails($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereSubject($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereSystemActor($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyLogEntry whereUserId($value)
     */
    class PartyLogEntry extends \Eloquent {}
}

namespace App\Models{
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property array<array-key, mixed> $tokens
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     *
     * @method static \Database\Factories\InstanceThemeFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme whereTokens($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|InstanceTheme whereUpdatedAt($value)
     */
    class InstanceTheme extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Party\Models\Party;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $party_id
     * @property string $mod_id
     * @property bool $enabled
     * @property array<string, mixed>|null $settings stored values; secrets are encrypted
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Party $party
     *
     * @method static \Database\Factories\PartyModFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereEnabled($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereModId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereSettings($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMod whereUpdatedAt($value)
     */
    class PartyMod extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Party\Models\Party;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $party_id
     * @property array<string, mixed> $payload
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Party $party
     *
     * @method static \Database\Factories\PartyStatFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat wherePayload($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyStat whereUpdatedAt($value)
     */
    class PartyStat extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Membership\Models\PartyMember;
    use App\Domain\Party\Models\Party;
    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $party_id
     * @property int|null $track_request_id
     * @property int|null $party_member_id
     * @property string $provider_track_id
     * @property string $title
     * @property list<string> $artists
     * @property int $duration_ms
     * @property string|null $selection_mode
     * @property int|null $selection_score
     * @property CarbonImmutable $played_at
     * @property int|null $likes
     * @property int|null $dislikes
     * @property int|null $my_rating
     * @property string|null $album
     * @property string|null $artwork_url
     * @property bool $explicit
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read Collection<int, Rating> $memberRatings
     * @property-read int|null $member_ratings_count
     * @property-read Party $party
     * @property-read TrackRequest|null $request
     * @property-read PartyMember|null $requester
     *
     * @method static \Database\Factories\PlayFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereAlbum($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereArtists($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereArtworkUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereDurationMs($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereExplicit($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play wherePartyMemberId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play wherePlayedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereProviderTrackId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereSelectionMode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereSelectionScore($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereTitle($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereTrackRequestId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play withHistoryRelations()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Play withRatingSummary(?\App\Domain\Membership\Models\PartyMember $viewer = null)
     */
    class Play extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Membership\Models\PartyMember;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $play_id
     * @property int $party_member_id
     * @property int $value
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read PartyMember $member
     * @property-read Play $play
     *
     * @method static \Database\Factories\RatingFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating wherePartyMemberId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating wherePlayId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|Rating whereValue($value)
     */
    class Rating extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Membership\Models\PartyMember;
    use Illuminate\Support\Carbon;

    /**
     * @property int $id
     * @property int $track_request_id
     * @property int $party_member_id
     * @property int $value
     * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
     * @property-read PartyMember $member
     * @property-read TrackRequest $request
     *
     * @method static \Database\Factories\RequestVoteFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote wherePartyMemberId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote whereTrackRequestId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote whereUpdatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|RequestVote whereValue($value)
     */
    class RequestVote extends \Eloquent {}
}

namespace App\Models{
    use App\Domain\Membership\Models\PartyMember;
    use App\Domain\Party\Models\Party;
    use Illuminate\Database\Eloquent\Collection;

    /**
     * @property RequestStatus $status
     * @property list<string> $artists
     * @property int|null $score
     * @property int|null $my_vote
     * @property Carbon $updated_at
     * @property Carbon|null $decided_at
     * @property string|null $rejection_reason
     * @property int|null $upvotes
     * @property int|null $downvotes
     * @property int|null $party_member_id
     * @property string $provider_track_id
     * @property int $duration_ms
     * @property CarbonImmutable|null $not_before
     * @property CarbonImmutable|null $up_next_at
     * @property CarbonImmutable|null $enqueued_at
     * @property CarbonImmutable|null $started_at
     * @property string|null $selection_mode
     * @property int|null $selection_score
     * @property int $id
     * @property int $party_id
     * @property string|null $isrc
     * @property string $title
     * @property string|null $album
     * @property string|null $artwork_url
     * @property bool $explicit
     * @property int|null $decided_by_member_id
     * @property \Illuminate\Support\Carbon|null $created_at
     * @property-read Party $party
     * @property-read Play|null $play
     * @property-read PartyMember|null $requester
     * @property-read Collection<int, RequestVote> $votes
     * @property-read int|null $votes_count
     *
     * @method static \Database\Factories\TrackRequestFactory factory($count = null, $state = [])
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest query()
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereAlbum($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereArtists($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereArtworkUrl($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereCreatedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereDecidedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereDecidedByMemberId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereDurationMs($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereEnqueuedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereExplicit($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereIsrc($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereNotBefore($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest wherePartyId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest wherePartyMemberId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereProviderTrackId($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereRejectionReason($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereSelectionMode($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereSelectionScore($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereStartedAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereStatus($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereTitle($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereUpNextAt($value)
     * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackRequest whereUpdatedAt($value)
     */
    class TrackRequest extends \Eloquent {}
}
