<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperParty
 */
class Party extends Model
{
    use HasFactory, ToString;

    protected $attributes = [
        'allow_requests' => true,
        'explicit' => true,
        'downvotes' => true,
        'process_requests' => true,
    ];

    public function toStringName(): string
    {
        return $this->code;
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    public function members(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PlayedSong::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function updateState(): void
    {

    }

    public function getState(): object
    {
        return (object)[
            'code' => $this->code,
            'name' => $this->name,
        ];
    }

    public function updateDeviceName(): void
    {
        if (!$this->device_id) {
            return;
        }

        $devices = $this->user->getDevices();
        foreach ($devices as $device) {
            if ($device->id == $this->device_id) {
                $this->device_name = $device->name;
                return;
            }
        }

        $this->device_name = 'Unknown';
    }

    public function updatePlaylist(): void
    {
        $api = $this->user->getSpotifyApi();
        if (!$this->playlist_id) {
            $playlist = $api->createPlaylist($api->me()->id, [
                'name' => "Music Party - {$this->code}",
                'description' => 'Automatically controlled Music Party playlist',
                'public' => true,
            ]);
            $this->playlist_id = $playlist->id;
            $this->save();
        } else {
            $playlist = $api->getPlaylist($this->playlist_id);
            if ($playlist->name !== "Music Party - {$this->code}") {
                $api->updatePlaylist($this->playlist_id, [
                    'name' => "Music Party - {$this->code}",
                ]);
            }
        }
    }
}
