<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Party\FallbackPlaylistGate;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class UpdatePartySettings
{
    private const array GATE_SETTINGS = ['fallback_playlist_id', 'explicit', 'min_song_length', 'max_song_length', 'no_repeat_interval'];

    public function __construct(private RecordPartyLogEntry $record, private FallbackPlaylistGate $gate) {}

    /**
     * @param  array<string, mixed>  $settings
     * @return array{party: Party, warning: ?FallbackPlaylistCheck}
     */
    public function __invoke(User $actor, Party $party, array $settings): array
    {
        return DB::transaction(function () use ($actor, $party, $settings): array {
            $changed = [];

            foreach ($settings as $key => $value) {
                $old = $party->getAttribute($key);

                if ($old === $value) {
                    continue;
                }

                $party->forceFill([$key => $value])->save();
                $changed[] = $key;

                ($this->record)($party, 'party.settings_changed', $actor, $key, ['old' => $old, 'new' => $value]);
            }

            $affectsGate = array_intersect($changed, self::GATE_SETTINGS) !== [];

            if (! $affectsGate) {
                return ['party' => $party, 'warning' => null];
            }

            $check = $this->gate->check($party);

            if ($check->passes()) {
                return ['party' => $party, 'warning' => null];
            }

            ($this->record)($party, 'party.fallback_playlist_invalid', $actor, details: [
                'playable' => $check->playable,
                'required' => $check->required,
            ]);

            return ['party' => $party, 'warning' => $check];
        });
    }
}
