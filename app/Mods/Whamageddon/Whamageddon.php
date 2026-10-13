<?php

namespace App\Mods\Whamageddon;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Mods\PartyMods;
use App\Services\ModDataStore;
use App\Services\ModSettingsStore;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use App\Services\QueueSelector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class Whamageddon
{
    protected const CODE = WhamageddonMod::CODE;

    protected bool $isEnabled = false;

    protected ModSettingsStore $settings;

    public function __construct(protected Party $party, ?ModSettingsStore $settings = null)
    {
        $this->settings = $settings ?? app(ModSettingsStore::class);
    }

    public function hourly(): void
    {
        if (! $this->party->active) {
            Log::info("{$this->party} Whamageddon: Party is not active");

            return;
        }
        if (! app(PartyMods::class)->isEnabled($this->party, self::CODE)) {
            Log::info("{$this->party} Whamageddon: Whamageddon is not enabled");

            return;
        }
        $hour = $this->partyNow()->hour;
        if ($hour >= 2 && $hour < 10) {
            Log::info("{$this->party} Whamageddon: Skipping upvote because it's between 2AM and 10AM");

            return;
        }
        $upcoming = $this->getUpcomingSong();
        if ($upcoming === null) {
            Log::info("{$this->party} Whamageddon: No upcoming song found, adding to party");
            $this->addToParty();
        } else {
            Log::info("{$this->party} Whamageddon: Upcoming song found");
            if ($upcoming->queued_at !== null) {
                $cutoff = $upcoming->queued_at->addHours(2);
                if (CarbonImmutable::now() >= $cutoff) {
                    Log::info("{$this->party} Whamageddon: Song was last played 2 hours ago - time for another round!");
                    $this->addToParty();
                } else {
                    Log::info("{$this->party} Whamageddon: Upcoming song has already been played, waiting until {$cutoff->toIso8601String()}");
                }
            } else {
                Log::info("{$this->party} Whamageddon: Adding an upvote");
                $this->addUpvote($upcoming);
            }
            $this->delayPlayback($upcoming);
        }
    }

    protected function delayPlayback(UpcomingSong $upcoming): void
    {
        $now = $this->partyNow();
        $hour = $now->hour;
        if ($hour >= 23) {
            $upcoming->not_before = Carbon::instance($now->startOfDay()->addDay()->hour(10))->utc();
            Log::info("{$this->party} Whamageddon: Delaying Playback until 10AM tomorrow");
            $upcoming->save();
        } elseif ($upcoming->not_before && $hour >= 10) {
            $upcoming->not_before = null;
            $upcoming->save();
            Log::info("{$this->party} Whamageddon: After 10AM, allowing playback");
        }
    }

    protected function partyNow(): CarbonImmutable
    {
        return CarbonImmutable::now(app(PartyTimezone::class)->for($this->party));
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
            $this->storeDecoration($upcoming, 'fallback_override', null);
            $this->storeDecoration($upcoming, 'css_classes', null);
            $upcoming->delete();
        }
        $this->settings->delete($this->party, self::CODE, 'upcoming_song_id');
    }

    protected function addToParty(): void
    {
        $trackId = $this->settings->value($this->party, self::CODE, 'track');
        if ($trackId === null) {
            Log::warning("{$this->party} Whamageddon: No track configured to add");

            return;
        }
        $upcoming = $this->party->upcoming()
            ->whereNull('queued_at')
            ->whereHas('song', function ($query) use ($trackId) {
                $query->where('provider_track_id', $trackId);
            })->first();
        if ($upcoming === null) {
            $track = app(MusicProviderFactory::class)->forParty($this->party, Purpose::Search)->getTrack($trackId);
            if ($track === null) {
                Log::warning("{$this->party} Whamageddon: Unable to find {$trackId} on the music provider");

                return;
            }
            $song = Song::fromTrack($track);
        } else {
            $song = $upcoming->song;
        }
        $attributes = ['user_id' => null];
        if ($upcoming?->score_adjustment === 0) {
            $attributes['score_adjustment'] = 1;
        }
        $upcoming = app(QueueSelector::class)->addRequest($this->party, $song, null, $attributes);
        $this->storeDecoration($upcoming, 'fallback_override', $this->settings->value($this->party, self::CODE, 'fallback_name'));
        $this->storeDecoration($upcoming, 'css_classes', $this->settings->value($this->party, self::CODE, 'css_classes'));
        Log::info("{$this->party} Whamageddon: Added song to party");
        $this->settings->set($this->party, self::CODE, 'upcoming_song_id', $upcoming->id);
    }

    protected function storeDecoration(UpcomingSong $upcoming, string $key, mixed $value): void
    {
        if ($value === null) {
            app(ModDataStore::class)->forget($upcoming, self::CODE, $key);

            return;
        }
        app(ModDataStore::class)->set($upcoming, self::CODE, $key, $value);
    }

    protected function addUpvote(UpcomingSong $upcoming): void
    {
        if ($upcoming->queued_at !== null) {
            Log::info("{$this->party} Whamageddon: The song has already been played");
        }
        $upcoming->score_adjustment += $this->settings->value($this->party, self::CODE, 'score_per_hour', 1);
        $upcoming->updateScore();
        $sign = '';
        if ($upcoming->score_adjustment > 0) {
            $sign = '+';
        }
        Log::info("{$this->party} Whamageddon: Vote added, now at {$sign}{$upcoming->score_adjustment}");
    }

    protected function getUpcomingSong(): ?UpcomingSong
    {
        $upcoming = $this->settings->value($this->party, self::CODE, 'upcoming_song_id');
        if ($upcoming === null || ! $upcoming) {
            return null;
        }

        return $this->party->upcoming()->whereId($upcoming)->first();
    }
}
