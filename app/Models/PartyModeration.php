<?php

namespace App\Models;

use App\Enums\PartyModerationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * @property int $id
 * @property int $party_id
 * @property PartyModerationType $type
 * @property string $value
 * @property string|null $notes
 * @property bool $enabled
 * @property bool $regex
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Party $party
 *
 * @method static \Database\Factories\PartyModerationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyModeration whereValue($value)
 *
 * @mixin \Eloquent
 */
class PartyModeration extends Model
{
    use HasFactory;

    protected $casts = [
        'enabled' => 'boolean',
        'regex' => 'boolean',
        'type' => PartyModerationType::class,
    ];

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function match(stdClass $spotifyTrack): bool
    {
        $methodName = 'check'.substr($this->type->name, 2);
        if (method_exists($this, $methodName)) {
            $result = $this->{$methodName}($spotifyTrack);
            if ($result === true) {
                Log::info("[PartyModeration:{$this->id}] has matched {$spotifyTrack->id}");
            }

            return $result;
        }

        return false;
    }

    protected function checkName(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'name')) {
            return false;
        }

        return $this->regexTest($spotifyTrack->name);
    }

    protected function checkArtist(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'artists') || ! is_iterable($spotifyTrack->artists)) {
            return false;
        }
        foreach ($spotifyTrack->artists as $artist) {
            if ($this->regexTest($artist->name)) {
                return true;
            }
        }

        return false;
    }

    protected function checkArtistId(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'artists') || ! is_iterable($spotifyTrack->artists)) {
            return false;
        }
        foreach ($spotifyTrack->artists as $artist) {
            if ($this->regexTest($artist->id, false)) {
                return true;
            }
        }

        return false;
    }

    protected function checkId(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'id')) {
            return false;
        }

        return $this->regexTest($spotifyTrack->id, false);
    }

    protected function checkAlbum(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'album') || ! is_object($spotifyTrack->album) || ! property_exists($spotifyTrack->album, 'name')) {
            return false;
        }

        return $this->regexTest($spotifyTrack->album->name);
    }

    protected function checkAlbumId(stdClass $spotifyTrack): bool
    {
        if (! property_exists($spotifyTrack, 'album') || ! is_object($spotifyTrack->album) || ! property_exists($spotifyTrack->album, 'id')) {
            return false;
        }

        return $this->regexTest($spotifyTrack->album->id, false);
    }

    protected function checkISRC(stdClass $spotifyTrack): bool
    {
        if ($spotifyTrack->external_ids?->isrc === null) {
            return false;
        }

        return $this->regexTest($spotifyTrack->external_ids->isrc);
    }

    public function getMessage(): string
    {
        switch ($this->type) {
            case PartyModerationType::mtAlbum:
            case PartyModerationType::mtAlbumId:
                return 'Album is not allowed';

            case PartyModerationType::mtArtist:
            case PartyModerationType::mtArtistId:
                return 'Artist is not allowed';

            default:
                return 'Track is not allowed';
        }
    }

    public const MAX_REGEX_LENGTH = 255;

    public const REGEX_BACKTRACK_LIMIT = 10000;

    public static function isValidRegex(string $pattern): bool
    {
        if (strlen($pattern) > self::MAX_REGEX_LENGTH) {
            return false;
        }

        return @preg_match($pattern, '') !== false;
    }

    protected function regexTest(mixed $toCheck, $caseInsensitive = true): bool
    {
        $toCheck = (string) $toCheck;
        if ($this->regex) {
            return $this->boundedMatch($this->value, $toCheck);
        }

        $filter = '/^'.str_replace('\\*', '(.*)', preg_quote($this->value, '/')).'$/';
        if ($caseInsensitive) {
            $filter .= 'i';
        }

        return $this->boundedMatch($filter, $toCheck);
    }

    protected function boundedMatch(string $pattern, string $subject): bool
    {
        $previousBacktrackLimit = ini_set('pcre.backtrack_limit', (string) self::REGEX_BACKTRACK_LIMIT);
        $previousJit = ini_set('pcre.jit', '0');
        try {
            $result = @preg_match($pattern, $subject);
            if ($result === false) {
                Log::warning("[PartyModeration:{$this->id}] pattern failed: ".preg_last_error_msg());

                return false;
            }

            return $result > 0;
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previousBacktrackLimit);
            ini_set('pcre.jit', (string) $previousJit);
        }
    }
}
