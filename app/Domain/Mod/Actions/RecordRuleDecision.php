<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Data\RuleDecision;
use App\Domain\Mod\RuleOutcome;
use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;

readonly class RecordRuleDecision
{
    public function __construct(private RecordPartyLogEntry $record) {}

    public function __invoke(Party $party, RuleDecision $decision, TrackData $track, ?int $requestId = null): void
    {
        foreach ($decision->failures as $failure) {
            ($this->record)($party, 'mod.rule_failed', subject: $track->name, details: [
                'mod' => $failure['mod']->mod->name(),
                'mod_id' => $failure['mod']->mod->id(),
                'track' => $track->providerTrackId,
                'error' => $failure['error'],
            ], systemActor: $failure['mod']->systemActor());
        }

        if ($decision->decidedBy === null || $decision->outcome === RuleOutcome::Accept) {
            return;
        }

        ($this->record)($party, $decision->outcome === RuleOutcome::Reject ? 'mod.request_rejected' : 'mod.request_held', subject: $track->name, details: [
            'mod' => $decision->decidedBy->mod->name(),
            'mod_id' => $decision->decidedBy->mod->id(),
            'track' => $track->providerTrackId,
            'reason' => $decision->reason,
            'request_id' => $requestId,
        ], systemActor: $decision->decidedBy->systemActor());
    }
}
