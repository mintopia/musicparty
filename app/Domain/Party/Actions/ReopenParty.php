<?php

namespace App\Domain\Party\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Events\PartyStateChanged;
use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Support\Facades\DB;

readonly class ReopenParty
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

            if (! in_array($old, [PartyState::Ended], true)) {
                throw InvalidPartyTransition::for($old, 'reopened');
            }

            $party->forceFill(['state' => PartyState::Paused])->save();

            ($this->record)($party, 'party.reopened', $actor, details: ['old' => $old->value, 'new' => PartyState::Paused->value]);

            DB::afterCommit(fn () => PartyStateChanged::dispatch($party, $old, PartyState::Paused));

            return $party;
        });
    }
}
