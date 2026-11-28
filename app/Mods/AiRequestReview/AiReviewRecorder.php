<?php

namespace App\Mods\AiRequestReview;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestOutcome;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiReviewRecorder
{
    public function record(AiReviewTrace $trace, RequestDecision $decision, float $latencyMs): ?AiReview
    {
        try {
            $outcome = $trace->errorClass !== null ? AiReviewOutcome::Error : ($trace->skipReason !== null ? AiReviewOutcome::Skipped : match ($decision->outcome) {
                RequestOutcome::Allow => AiReviewOutcome::Allow,
                RequestOutcome::Flag => AiReviewOutcome::Flag,
                RequestOutcome::Reject => AiReviewOutcome::Reject,
            });

            return AiReview::query()->create([
                'party_id' => $trace->party->id,
                'user_id' => $trace->member->user_id,
                'party_member_id' => $trace->member->id,
                'track_provider' => $trace->track->providerId,
                'track_id' => $trace->track->id,
                'track_title' => $trace->track->name,
                'track_artists' => implode(', ', array_map(fn ($artist): string => $artist->name, $trace->track->artists)),
                'driver' => $trace->driver,
                'model' => $trace->model,
                'endpoint_host' => $trace->endpointHost,
                'request' => $this->scrubbed($trace->request, $trace->secret),
                'response' => $trace->response === null ? null : $this->scrubbed($trace->response, $trace->secret),
                'outcome' => $outcome,
                'reason' => $trace->skipReason ?? $decision->reason,
                'score' => $trace->score,
                'confidence' => $trace->confidence,
                'criterion' => $trace->criterion,
                'criterion_confidence' => $trace->criterionConfidence,
                'latency_ms' => (int) round($latencyMs),
                'prompt_tokens' => $trace->promptTokens,
                'completion_tokens' => $trace->completionTokens,
                'total_tokens' => $trace->totalTokens,
                'error_class' => $trace->errorClass,
                'error_message' => $trace->errorMessage,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('AI Request Review history could not be saved: '.$e::class);

            return null;
        }
    }

    /**
     * Links the review made for this member and track to the Request it produced.
     */
    public function linkRequest(UpcomingSong $request, ?User $user, string $trackId): void
    {
        if ($user === null) {
            return;
        }
        $review = AiReview::query()
            ->where('party_id', $request->party_id)
            ->where('user_id', $user->id)
            ->where('track_id', $trackId)
            ->whereNull('upcoming_song_id')
            ->latest('id')
            ->first();
        $review?->forceFill(['upcoming_song_id' => $request->id])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function scrubbed(array $data, ?string $secret): array
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($secret !== null && $secret !== '') {
            $json = str_replace([$secret, trim((string) json_encode($secret), '"')], AiReviewLog::REDACTED, $json);
        }
        $json = (string) preg_replace('/Bearer\s+[^\s"\'\\\\]+/i', 'Bearer '.AiReviewLog::REDACTED, $json);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
