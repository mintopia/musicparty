<?php

namespace Tests\Support;

use App\Models\LinkedAccount;
use App\Models\Mod;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\ModSettingsStore;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class WhamageddonFixtures
{
    public const TRACK_ID = 'whamtrack';

    public static function install(): void
    {
        Artisan::call('mods:sync');
    }

    public const DEFAULT_TRACK_ID = '2FRnf9qhLbvw8fu4IBXx78';

    public static function fake(): FakeMusicProvider
    {
        app()->forgetInstance(FakeMusicProvider::class);

        return app(FakeMusicProvider::class)
            ->withTrack(FakeFallbackPlaylist::track(self::TRACK_ID))
            ->withTrack(FakeFallbackPlaylist::track(self::DEFAULT_TRACK_ID));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function party(array $settings = [], bool $active = true): Party
    {
        $party = Party::factory()->create(['provider_id' => 'fake']);
        if ($active) {
            Party::whereKey($party->id)->update(['active' => true]);
        }
        LinkedAccount::query()->update(['access_token_expires_at' => now()->addYears(5)]);
        $party->refresh();
        if (! empty($settings['enabled'])) {
            DB::table('party_mods')->insert([
                'party_id' => $party->id,
                'mod_id' => Mod::whereCode('whamageddon')->firstOrFail()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        unset($settings['enabled']);
        foreach ($settings as $code => $value) {
            app(ModSettingsStore::class)->set($party, 'whamageddon', $code, $value);
        }

        return $party;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function upcoming(Party $party, array $attributes = [], bool $track = true): UpcomingSong
    {
        $song = Song::where('provider_track_id', self::TRACK_ID)->first() ?? Song::factory()->create(['provider_track_id' => self::TRACK_ID]);
        $upcoming = UpcomingSong::factory()->waiting()->create(array_merge([
            'party_id' => $party->id,
            'song_id' => $song->id,
            'user_id' => null,
            'score_adjustment' => 0,
        ], $attributes));
        if ($track) {
            app(ModSettingsStore::class)->set($party, 'whamageddon', 'upcoming_song_id', $upcoming->id);
        }

        return $upcoming;
    }
}
