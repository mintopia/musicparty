<?php

namespace App\Models;

use App\Enums\PartyModerationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * @mixin IdeHelperPartyModeration
 */
class PartyModeration extends Model
{
    protected $casts = [
        'type' => PartyModerationType::class,
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function match(object $spotifyTrack): bool
    {
        $methodName = 'check' . substr($this->type->name, 2);
        if (method_exists($this, $methodName)) {
            $result = $this->{$methodName}($spotifyTrack);
            if ($result === true) {
                Log::info("[PartyModeration:{$this->id}] has matched {$spotifyTrack->id}");
            }
            return $result;
        }
        return false;
    }

    protected function checkName(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'name')) {
            return false;
        }
        return $this->regexTest($spotifyTrack->name);
    }

    protected function checkArtist(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'artists') || !is_iterable($spotifyTrack->artists)) {
            return false;
        }
        foreach ($spotifyTrack->artists as $artist) {
            if ($this->regexTest($artist->name)) {
                return true;
            }
        }
        return false;
    }

    protected function checkArtistId(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'artists') || !is_iterable($spotifyTrack->artists)) {
            return false;
        }
        foreach ($spotifyTrack->artists as $artist) {
            if ($this->regexTest($artist->id, false)) {
                return true;
            }
        }
        return false;
    }

    protected function checkId(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'id')) {
            return false;
        }
        return $this->regexTest($spotifyTrack->id, false);
    }

    protected function checkAlbum(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'album') || !is_object($spotifyTrack->album) || !property_exists($spotifyTrack->album, 'name')) {
            return false;
        }
        return $this->regexTest($spotifyTrack->album->name);
    }

    protected function checkAlbumId(object $spotifyTrack): bool
    {
        if (!property_exists($spotifyTrack, 'album') || !is_object($spotifyTrack->album) || !property_exists($spotifyTrack->album, 'id')) {
            return false;
        }
        return $this->regexTest($spotifyTrack->album->id, false);
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

    protected function regexTest(mixed $toCheck, $caseInsensitive = true): bool
    {
        if ($this->regex) {
            $result = @preg_match($this->value, $toCheck);
            if ($result !== false) {
                return $result > 0;
            }
        }

        // Treat it like a wildcard string match
        $filter = '/^' . str_replace('*', '(.*)', $this->value) . '$/';
        if ($caseInsensitive) {
            $filter .= 'i';
        }
        $result = @preg_match($filter, $toCheck);
        if ($result !== false) {
            return $result > 0;
        }
        return $toCheck == $this->value;
    }
}
