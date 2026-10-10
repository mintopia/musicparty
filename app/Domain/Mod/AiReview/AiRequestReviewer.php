<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\ResolvePendingRequestForMod;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Throwable;

readonly class AiRequestReviewer
{
    public function __construct(
        private EnabledMods $mods,
        private AiReviewClassifier $classifier,
        private ResolvePendingRequestForMod $resolve,
        private RecordPartyLogEntry $record,
    ) {}

    public function review(Party $party, int $requestId): void
    {
        [$enabled, $request] = $this->load($party, $requestId);

        if ($enabled === null || $request === null) {
            return;
        }

        try {
            $score = $this->classifier->score($enabled->context, TrackSummary::fromRequest($request));
        } catch (Throwable $exception) {
            report($exception);
            $this->applyFailureBehaviour($party, $enabled, $request, $exception->getMessage());

            return;
        }

        $this->apply($party, $enabled, $request, $score);
    }

    public function fail(Party $party, int $requestId, string $error): void
    {
        [$enabled, $request] = $this->load($party, $requestId);

        if ($enabled !== null && $request !== null) {
            $this->applyFailureBehaviour($party, $enabled, $request, $error);
        }
    }

    /**
     * @return array{0: EnabledMod|null, 1: TrackRequest|null}
     */
    private function load(Party $party, int $requestId): array
    {
        $enabled = null;

        foreach ($this->mods->for($party) as $candidate) {
            if ($candidate->mod->id() === AiRequestReviewMod::ID) {
                $enabled = $candidate;
            }
        }

        $request = TrackRequest::query()->where('party_id', $party->id)->whereKey($requestId)->where('status', RequestStatus::Pending)->first();

        return [$enabled, $request];
    }

    private function apply(Party $party, EnabledMod $enabled, TrackRequest $request, ReviewScore $score): void
    {
        $percent = $score->percent();
        $settings = $enabled->context->settings;

        if ($percent >= (int) $settings['reject_threshold']) {
            ($this->resolve)($party, $request->id, RequestStatus::Rejected, $enabled->systemActor(), "Rated {$percent}/100 for unsuitability by AI review.");

            return;
        }

        if ($percent >= (int) $settings['hold_threshold']) {
            $this->logHold($party, $enabled, $request, "Rated {$percent}/100 for unsuitability by AI review.");

            return;
        }

        ($this->resolve)($party, $request->id, RequestStatus::Queued, $enabled->systemActor());
    }

    private function applyFailureBehaviour(Party $party, EnabledMod $enabled, TrackRequest $request, string $error): void
    {
        ($this->record)($party, 'mod.rule_failed', subject: $request->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'request_id' => $request->id,
            'error' => $error,
        ], systemActor: $enabled->systemActor());

        match ($enabled->context->settings['failure_behaviour'] ?? 'hold') {
            'accept' => ($this->resolve)($party, $request->id, RequestStatus::Queued, $enabled->systemActor()),
            'reject' => ($this->resolve)($party, $request->id, RequestStatus::Rejected, $enabled->systemActor(), 'AI review was unavailable.'),
            default => $this->logHold($party, $enabled, $request, 'AI review was unavailable.'),
        };
    }

    private function logHold(Party $party, EnabledMod $enabled, TrackRequest $request, string $reason): void
    {
        ($this->record)($party, 'mod.request_held', subject: $request->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'track' => $request->provider_track_id,
            'reason' => $reason,
            'request_id' => $request->id,
        ], systemActor: $enabled->systemActor());
    }
}
