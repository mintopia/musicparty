<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\Data\RuleDecision;
use App\Domain\Mod\EnabledMods;
use App\Domain\Mod\RuleOutcome;
use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Models\Party;
use Throwable;

readonly class EvaluateRequestRules
{
    public function __construct(private EnabledMods $mods) {}

    /**
     * Reject beats hold beats accept; a rule that throws or times out follows its Mod's failure_behaviour setting.
     */
    public function __invoke(Party $party, PartyMember $member, TrackData $track): RuleDecision
    {
        $winner = new RuleDecision(RuleOutcome::Accept);
        $failures = [];

        foreach ($this->mods->for($party) as $enabled) {
            foreach ($enabled->mod->requestRules() as $rule) {
                try {
                    $verdict = $rule->judge($enabled->context, $member, $track);
                    $outcome = $verdict->outcome;
                    $reason = $verdict->reason;
                } catch (Throwable $exception) {
                    report($exception);
                    $failures[] = ['mod' => $enabled, 'error' => $exception->getMessage()];
                    $outcome = $this->failureOutcome($enabled);
                    $reason = 'The rule failed to run.';
                }

                if ($outcome->severity() > $winner->outcome->severity()) {
                    $winner = new RuleDecision($outcome, $enabled, $reason);
                }
            }
        }

        return new RuleDecision($winner->outcome, $winner->decidedBy, $winner->reason, $failures);
    }

    private function failureOutcome(EnabledMod $enabled): RuleOutcome
    {
        return match ($enabled->context->settings['failure_behaviour'] ?? 'accept') {
            'hold' => RuleOutcome::Hold,
            'reject' => RuleOutcome::Reject,
            default => RuleOutcome::Accept,
        };
    }
}
