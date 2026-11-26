<?php

namespace App\Mods\AiRequestReview;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiReviewLog
{
    public const CHANNEL = 'ai-review';

    public const REDACTED = '[redacted]';

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $event, array $context = []): void
    {
        Log::channel(self::CHANNEL)->debug($event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function failure(Throwable $e, ?string $secret, array $context = []): void
    {
        Log::channel(self::CHANNEL)->warning('ai-review.error', $context + [
            'error_class' => $e::class,
            'error_message' => $this->redact($e->getMessage(), $secret),
        ]);
    }

    public function redact(string $text, ?string $secret = null): string
    {
        if ($secret !== null && $secret !== '') {
            $text = str_replace($secret, self::REDACTED, $text);
        }

        return Str::limit((string) preg_replace('/Bearer\s+[^\s"\']+/i', 'Bearer '.self::REDACTED, $text), 2000);
    }

    public function elapsedMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 1);
    }
}
