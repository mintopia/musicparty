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


namespace App\Models{
/**
 * App\Models\Album
 *
 * @property int $id
 * @property string $name
 * @property string $spotify_id
 * @property string $image_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperAlbum {}
}

namespace App\Models{
/**
 * App\Models\Artist
 *
 * @property int $id
 * @property string $name
 * @property string $spotify_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Artist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist query()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperArtist {}
}

namespace App\Models{
/**
 * App\Models\LinkedAccount
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $social_provider_id
 * @property string|null $external_id
 * @property string|null $name
 * @property string|null $avatar_url
 * @property string|null $email
 * @property mixed|null $access_token
 * @property mixed|null $refresh_token
 * @property string|null $access_token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SocialProvider|null $provider
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereAccessTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereSocialProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LinkedAccount whereUserId($value)
 * @mixin \Eloquent
 */
	class IdeHelperLinkedAccount {}
}

namespace App\Models{
/**
 * App\Models\Party
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $user_id
 * @property int $active
 * @property int $queue
 * @property int $force
 * @property int $explicit
 * @property int $allow_requests
 * @property int $process_requests
 * @property int $downvotes
 * @property int|null $max_song_length
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $playlist_id
 * @property string|null $backup_playlist_id
 * @property string|null $backup_playlist_name
 * @property string|null $last_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartyMember> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Party newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party query()
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereAllowRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereBackupPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereBackupPlaylistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereExplicit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereForce($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereLastUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereMaxSongLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party wherePlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereProcessRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereQueue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUserId($value)
 * @mixin \Eloquent
 */
	class IdeHelperParty {}
}

namespace App\Models{
/**
 * App\Models\PartyMember
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int $party_id
 * @property int $canvote
 * @property int $banned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Party $party
 * @property-read \App\Models\PartyMemberRole $role
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereBanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereCanvote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMember whereUserId($value)
 * @mixin \Eloquent
 */
	class IdeHelperPartyMember {}
}

namespace App\Models{
/**
 * App\Models\PartyMemberRole
 *
 * @property int $id
 * @property string $code
 * @property string $text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartyMember> $member
 * @property-read int|null $member_count
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperPartyMemberRole {}
}

namespace App\Models{
/**
 * App\Models\ProviderSetting
 *
 * @property int $id
 * @property string $provider_type
 * @property int $provider_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property \App\Enums\SettingType $type
 * @property int $encrypted
 * @property string|null $validation
 * @property mixed|null $value
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $provider
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereProviderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereValidation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProviderSetting whereValue($value)
 * @mixin \Eloquent
 */
	class IdeHelperProviderSetting {}
}

namespace App\Models{
/**
 * App\Models\Role
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperRole {}
}

namespace App\Models{
/**
 * App\Models\Setting
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $encrypted
 * @property int $hidden
 * @property mixed|null $value
 * @property string|null $validation
 * @property \App\Enums\SettingType $type
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereValidation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereValue($value)
 * @mixin \Eloquent
 */
	class IdeHelperSetting {}
}

namespace App\Models{
/**
 * App\Models\SocialProvider
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $provider_class
 * @property int $supports_auth
 * @property int $enabled
 * @property int $auth_enabled
 * @property int $can_be_renamed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinkedAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProviderSetting> $settings
 * @property-read int|null $settings_count
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereAuthEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereCanBeRenamed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereProviderClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereSupportsAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SocialProvider whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperSocialProvider {}
}

namespace App\Models{
/**
 * App\Models\Theme
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $readonly
 * @property int $active
 * @property string $primary
 * @property int $dark_mode
 * @property string $nav_background
 * @property string|null $css
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereCss($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereNavBackground($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme wherePrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereReadonly($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Theme whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperTheme {}
}

namespace App\Models{
/**
 * App\Models\User
 *
 * @property int $id
 * @property string $nickname
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $terms_agreed_at
 * @property int $first_login
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property int $suspended
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinkedAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Party> $parties
 * @property-read int|null $parties_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFirstLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTermsAgreedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class IdeHelperUser {}
}

