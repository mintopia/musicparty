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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereSocialProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkedAccount whereUserId($value)
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
 * @property int|null $downvotes_per_hour
 * @property int $weighted
 * @property string|null $history_playlist_id
 * @property int $trustscore
 * @property int|null $trusted_user_id
 * @property int $force
 * @property int $explicit
 * @property int $allow_requests
 * @property int $downvotes
 * @property int|null $min_song_length
 * @property int|null $max_song_length
 * @property int|null $no_repeat_interval
 * @property string|null $device_id
 * @property string|null $recent_device_id
 * @property string|null $device_name
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
 * @property-read \App\Models\User|null $trustedUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UpcomingSong> $upcoming
 * @property-read int|null $upcoming_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereAllowRequests($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereBackupPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereBackupPlaylistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDeviceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereDownvotesPerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereExplicit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereForce($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereHistoryPlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereLastUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMaxSongLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereMinSongLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereNoRepeatInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party wherePoll($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereRecentDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereShowQrcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereSongStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTrustedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereTrustscore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Party whereWeighted($value)
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
 * @property float $trustscore
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Party $party
 * @property-read \App\Models\PartyMemberRole $role
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereBanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCanvote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereTrustscore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUserId($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereDislikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereLikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong wherePlayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereRelinkedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating wherePlayedSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereValue($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDarkMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereNavBackground($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereReadonly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUpvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUserId($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMarket($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatusUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTermsAgreedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperVote {}
}

