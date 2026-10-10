<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;
use App\Domain\Queue\Events\RequestCreated;
use App\Jobs\ReviewRequestWithAi;
use Illuminate\Support\Facades\Cache;

class AiRequestReviewMod implements Mod
{
    public const string ID = 'ai-request-review';

    public static function pendingReviewKey(int $partyId, string $providerTrackId): string
    {
        return "ai-review:{$partyId}:{$providerTrackId}";
    }

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'AI Request Review';
    }

    public function description(): string
    {
        return 'Asks an AI classifier whether a requested Track suits the Party, then accepts it, rejects it, or holds it for a Host or Moderator.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('driver', 'AI driver', SettingKind::Choice, AiReviewDriver::OpenAi->value, options: array_column(AiReviewDriver::cases(), 'value'), required: true),
            new SettingDefinition('openai_api_key', 'OpenAI API key', SettingKind::Secret),
            new SettingDefinition('jev_api_key', 'Jev API key', SettingKind::Secret),
            new SettingDefinition('rubric', 'Review criteria', SettingKind::Text, 'Rate how unsuitable this track is for a general-audience party: offensive, hateful or sexually explicit content, or content that clashes with a social event.', required: true),
            new SettingDefinition('hold_threshold', 'Hold at or above (0-100)', SettingKind::Integer, 40, min: 0, max: 100),
            new SettingDefinition('reject_threshold', 'Reject at or above (0-100)', SettingKind::Integer, 80, min: 0, max: 100),
            new SettingDefinition('timeout_seconds', 'Review timeout (seconds)', SettingKind::Integer, 20, min: 5, max: 60),
            new SettingDefinition('failure_behaviour', 'When review fails or times out', SettingKind::Choice, 'hold', options: ['accept', 'hold', 'reject']),
        ];
    }

    public function requestRules(): array
    {
        return [new AiReviewRule];
    }

    public function scoreModifiers(): array
    {
        return [];
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function listeners(): array
    {
        return [
            RequestCreated::class => function (object $event, ModContext $context): void {
                if ($event instanceof RequestCreated && Cache::pull(self::pendingReviewKey($context->party->id, $event->request->provider_track_id))) {
                    ReviewRequestWithAi::dispatch($context->party->id, $event->request->id)->afterCommit();
                }
            },
        ];
    }
}
