<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Events\PartyStateChanged;
use App\Domain\Party\Exceptions\FallbackPlaylistInsufficient;
use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\PartyState;
use App\Jobs\StartPlayback;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class GoLiveParty
{
    public function __construct(private RecordPartyLogEntry $record, private FallbackPlaylistGate $gate) {}

    /**
     * @throws InvalidPartyTransition
     * @throws FallbackPlaylistInsufficient
     */
    public function __invoke(User $actor, Party $party): Party
    {
        $live = DB::transaction(function () use ($actor, $party): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);
            $old = $party->state;

            if (! in_array($old, [PartyState::Paused], true)) {
                throw InvalidPartyTransition::for($old, 'live');
            }

            $check = $this->gate->check($party);

            if (! $check->passes()) {
                throw FallbackPlaylistInsufficient::for($check);
            }

            $party->forceFill(['state' => PartyState::Live])->save();

            ($this->record)($party, 'party.went_live', $actor, details: ['old' => $old->value, 'new' => PartyState::Live->value]);

            DB::afterCommit(fn () => PartyStateChanged::dispatch($party, $old, PartyState::Live));

            return $party;
        });

        StartPlayback::dispatch($live->code);

        return $live;
    }
}
