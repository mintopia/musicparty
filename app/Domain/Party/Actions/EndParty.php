<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\PartyState;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class EndParty
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws InvalidPartyTransition
     */
    public function __invoke(User $actor, Party $party): Party
    {
        return DB::transaction(function () use ($actor, $party): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);
            $old = $party->state;

            if (! in_array($old, [PartyState::Live, PartyState::Paused], true)) {
                throw InvalidPartyTransition::for($old, 'ended');
            }

            $party->forceFill(['state' => PartyState::Ended])->save();

            ($this->record)($party, 'party.ended', $actor, details: ['old' => $old->value, 'new' => PartyState::Ended->value]);

            return $party;
        });
    }
}
