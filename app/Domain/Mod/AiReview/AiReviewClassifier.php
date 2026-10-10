<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Mod\Data\ModContext;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Score;
use Laravel\Ai\Responses\Data\ScoreAnswer;
use RuntimeException;

readonly class AiReviewClassifier
{
    private const string QUESTION = 'suitability';

    /**
     * Levels run from acceptable to unacceptable so a higher score is worse.
     *
     * @var list<string>
     */
    private const array LEVELS = [
        'Fully acceptable for this party.',
        'Borderline: a human should decide.',
        'Clearly unacceptable for this party.',
    ];

    public function score(ModContext $context, TrackSummary $track): ReviewScore
    {
        $driver = AiReviewDriver::from((string) $context->settings['driver']);
        $apiKey = (string) ($context->settings[$driver->keySetting()] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException("No API key configured for the {$driver->value} driver.");
        }

        $provider = 'ai-review-'.$context->party->id.'-'.substr(hash('sha256', $driver->value.$apiKey), 0, 12);
        config(["ai.providers.{$provider}" => [...(array) config("ai.providers.{$driver->provider()}"), 'key' => $apiKey]]);

        $rubric = (string) $context->settings['rubric'];
        $timeout = (int) $context->settings['timeout_seconds'];

        $answer = Classification::of($track->toArray())
            ->question(self::QUESTION, new Score($rubric, self::LEVELS))
            ->timeout($timeout)
            ->classify($provider, $driver->model())
            ->answer(self::QUESTION);

        if (! $answer instanceof ScoreAnswer) {
            throw new RuntimeException('The classifier did not return a score.');
        }

        return new ReviewScore(max(0.0, min(1.0, $answer->normalized())), $answer->confidence);
    }
}
