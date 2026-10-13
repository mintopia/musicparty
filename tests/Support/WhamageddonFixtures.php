<?php

namespace Tests\Support;

use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Models\Song;
use App\Models\UpcomingSong;
use Illuminate\Support\Facades\Artisan;

class WhamageddonFixtures
{
    public const TRACK_ID = 'whamtrack';

    public static function install(): void
    {
        Artisan::call('mods:whamageddon:install');
    }

    public static function fake(): FakeSpotify
    {
        $fake = FakeSpotify::install();
        $fake->respond('getTrack', FakeSpotify::track(self::TRACK_ID, 'Last Christmas'));

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function party(array $settings = [], bool $active = true): Party
    {
        $party = Party::factory()->create();
        if ($active) {
            Party::whereKey($party->id)->update(['active' => true]);
        }
        LinkedAccount::query()->update(['access_token_expires_at' => now()->addYears(5)]);
        $party->refresh();
        PartyModSetting::withoutEvents(function () use ($party, $settings): void {
            foreach ($settings as $code => $value) {
                $party->setModSetting('whamageddon', $code, $value);
            }
        });

        return $party;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function upcoming(Party $party, array $attributes = [], bool $track = true): UpcomingSong
    {
        $song = Song::whereSpotifyId(self::TRACK_ID)->first() ?? Song::factory()->create(['spotify_id' => self::TRACK_ID]);
        $upcoming = UpcomingSong::factory()->waiting()->create(array_merge([
            'party_id' => $party->id,
            'song_id' => $song->id,
            'user_id' => null,
            'score_adjustment' => 0,
        ], $attributes));
        if ($track) {
            PartyModSetting::withoutEvents(fn () => $party->setModSetting('whamageddon', 'upcoming_song_id', $upcoming->id));
        }

        return $upcoming;
    }
}
