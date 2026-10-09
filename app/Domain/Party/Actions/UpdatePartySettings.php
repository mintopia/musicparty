<?php

namespace App\Domain\Party\Actions;

use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class UpdatePartySettings
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @param  array{name?: string}  $settings
     */
    public function __invoke(User $actor, Party $party, array $settings): Party
    {
        return DB::transaction(function () use ($actor, $party, $settings): Party {
            foreach ($settings as $key => $value) {
                $old = $party->getAttribute($key);

                if ($old === $value) {
                    continue;
                }

                $party->forceFill([$key => $value])->save();

                ($this->record)($party, 'party.settings_changed', $actor, $key, ['old' => $old, 'new' => $value]);
            }

            return $party;
        });
    }
}
