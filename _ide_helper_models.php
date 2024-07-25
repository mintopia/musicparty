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
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $spotify_id
 * @property string $image_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Song> $songs
 * @property-read int|null $songs_count
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
	#[\AllowDynamicProperties]
	class IdeHelperAlbum {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $spotify_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Song> $songs
 * @property-read int|null $songs_count
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
	#[\AllowDynamicProperties]
	class IdeHelperArtist {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperLinkedAccount {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $user_id
 * @property int|null $song_id
 * @property \Illuminate\Support\Carbon|null $song_started_at
 * @property int $active
 * @property int $poll
 * @property int $show_qrcode
 * @property int $queue
 * @property int $force
 * @property int $explicit
 * @property int $allow_requests
 * @property int $downvotes
 * @property int|null $max_song_length
 * @property int|null $no_repeat_interval
 * @property string|null $device_id
 * @property string|null $recent_device_id
 * @property string|null $device_name
 * @property string|null $playlist_id
 * @property string|null $backup_playlist_id
 * @property string|null $backup_playlist_name
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlayedSong> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartyMember> $members
 * @property-read int|null $members_count
 * @property-read \App\Models\Song|null $song
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UpcomingSong> $upcoming
 * @property-read int|null $upcoming_count
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
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereNoRepeatInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party wherePlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party wherePoll($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereQueue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereRecentDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereShowQrcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereSongStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperParty {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperPartyMember {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $code
 * @property string $name
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
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyMemberRole whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPartyMemberRole {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $song_id
 * @property int $party_id
 * @property \Illuminate\Support\Carbon $played_at
 * @property int $likes
 * @property int $dislikes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $upcoming_song_id
 * @property int $rating
 * @property string|null $relinked_from
 * @property-read \App\Models\Party $party
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SongRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read \App\Models\Song $song
 * @property-read \App\Models\UpcomingSong|null $upcoming
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong query()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereDislikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereLikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong wherePlayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereRelinkedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSong whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPlayedSong {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperProviderSetting {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperRole {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperSetting {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperSocialProvider {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property int $length
 * @property int $album_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Album $album
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Artist> $artists
 * @property-read int|null $artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Party> $parties
 * @property-read int|null $parties_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlayedSong> $played
 * @property-read int|null $played_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UpcomingSong> $upcoming
 * @property-read int|null $upcoming_count
 * @method static \Illuminate\Database\Eloquent\Builder|Song newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Song newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Song query()
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSong {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $played_song_id
 * @property int $user_id
 * @property int $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PlayedSong $song
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating query()
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating wherePlayedSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SongRating whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSongRating {}
}

namespace App\Models{
/**
 * 
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
	#[\AllowDynamicProperties]
	class IdeHelperTheme {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $party_id
 * @property int $song_id
 * @property int $score
 * @property int $upvotes
 * @property int $downvotes
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $user_id
 * @property-read \App\Models\Party $party
 * @property-read \App\Models\PlayedSong|null $played
 * @property-read \App\Models\Song $song
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votes
 * @property-read int|null $votes_count
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong query()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereUpvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUpcomingSong {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $nickname
 * @property string $market
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $terms_agreed_at
 * @property int $first_login
 * @property \Illuminate\Support\Carbon|null $last_login
 * @property int $suspended
 * @property object|null $status
 * @property \Illuminate\Support\Carbon|null $status_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LinkedAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Party> $parties
 * @property-read int|null $parties_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartyMember> $partyMembers
 * @property-read int|null $party_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SongRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votes
 * @property-read int|null $votes_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFirstLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereMarket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatusUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereTermsAgreedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $upcoming_song_id
 * @property int $user_id
 * @property int $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\UpcomingSong $upcomingSong
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|Vote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Vote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Vote query()
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vote whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperVote {}
}

