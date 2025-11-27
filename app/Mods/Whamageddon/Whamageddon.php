<?php
namespace App\Mods\Whamageddon;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Whamageddon
{
    protected const CODE = 'whamageddon';

    protected bool $isEnabled = false;
    protected Collection $settings;

    public function __construct(protected Party $party)
    {
    }

    public function hourly(): void
    {
        if (!$this->party->active) {
            Log::info("{$this->party} Whamageddon: Party is not active");
            return;
        }
        if (!$this->party->getModSettingValue(self::CODE, 'enabled')) {
            Log::info("{$this->party} Whamageddon: Whamageddon is not enabled");
            return;
        }
        $hour = CarbonImmutable::now()->hour;
        if ($hour >= 2 && $hour < 10) {
            Log::info("{$this->party} Whamageddon: Skipping upvote because it's between 1AM and 9AM");
            return;
        }
        $upcoming = $this->getUpcomingSong();
        if ($upcoming === null) {
            Log::info("{$this->party} Whamageddon: No upcoming song found, adding to party");
            $this->addToParty();
        } else {
            Log::info("{$this->party} Whamageddon: Upcoming song found, adding upvote");
            $this->addUpvote($upcoming);
        }
    }

    public function enable(): void
    {
        Log::info("{$this->party} Whamageddon: Enabled");
        $this->isEnabled = true;
        $upcoming = $this->getUpcomingSong();
        if ($upcoming === null) {
            $this->addToParty();
        }
    }

    public function disable(): void
    {
        Log::info("{$this->party} Whamageddon: Disabling");
        $this->isEnabled = false;
        $upcoming = $this->getUpcomingSong();
        if ($upcoming) {
            Log::info("{$this->party} Whamageddon: Upcoming song found, removing from party");
            $upcoming->delete();
        }
        //$this->party->deleteModSetting(self::CODE, 'upcoming_song_id');
    }

    protected function addToParty(): void
    {
        $trackId = $this->party->getModSettingValue(self::CODE, 'spotify_id');
        if ($trackId === null) {
            Log::warning("{$this->party} Whamageddon: No Spotify track ID to add");
            return;
        }
        $upcoming = $this->party->upcoming()
            ->whereNull('queued_at')
            ->whereNull('user_id')
            ->whereHas('song', function ($query) use ($trackId) {
                $query->where('spotify_id', $trackId);
            })->first();
        if ($upcoming === null) {
            $track = $this->party->user->getSpotifyApi()->getTrack($trackId);
            if (!$track) {
                Log::warning("{$this->party} Whamageddon: Unable to find {$trackId} in Spotify");
                return;
            }
            $song = Song::fromSpotify($track);
            $upcoming = new UpcomingSong();
            $upcoming->song()->associate($song);
            $upcoming->party()->associate($this->party);
        }
        $upcoming->fallback_override = $this->party->getModSettingValue(self::CODE, 'fallback_name');
        $upcoming->css_classes = $this->party->getModSettingValue(self::CODE, 'css_classes');
        $upcoming->save();
        if (!$upcoming->save()) {
            Log::warning("{$this->party} Whamageddon: Unable to save upcoming song");
            return;
        }
        Log::info("{$this->party} Whamageddon: Added song to party");
        $this->party->setModSetting(self::CODE, 'upcoming_song_id', $upcoming->id);
    }

    protected function addUpvote(UpcomingSong $upcoming): void
    {
        if ($upcoming->queued_at !== null) {
            Log::info("{$this->party} Whamageddon: The song has already been played");
        }
        $upcoming->score_adjustment += $this->party->getModSettingValue(self::CODE, 'score_adjustment', 1);
        $upcoming->updateScore();
        $sign = '';
        if ($upcoming->score_adjustment > 0) {
            $sign = '+';
        }
        Log::info("{$this->party} Whamageddon: Vote added, now at {$sign}{$upcoming->score_adjustment}");
    }

    protected function getUpcomingSong(): ?UpcomingSong
    {
        $upcoming = $this->party->getModSettingValue(self::CODE, 'upcoming_song_id');
        if ($upcoming === null || !$upcoming) {
            return null;
        }
        return $this->party->upcoming()->whereId($upcoming)->first();
    }
}
