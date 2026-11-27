<?php

namespace App\Mods\AiRequestReview;

use App\Enums\AiReviewCriterion;
use App\Models\Party;
use App\Models\PartyMember;
use App\Services\Music\Data\Track;

final class AiReviewTrace
{
    public ?string $model = null;

    public ?string $endpointHost = null;

    /** @var array<string, mixed> */
    public array $request = [];

    /** @var array<string, mixed>|null */
    public ?array $response = null;

    public ?float $score = null;

    public ?float $confidence = null;

    public ?AiReviewCriterion $criterion = null;

    public ?float $criterionConfidence = null;

    public ?int $promptTokens = null;

    public ?int $completionTokens = null;

    public ?int $totalTokens = null;

    public ?string $errorClass = null;

    public ?string $errorMessage = null;

    public function __construct(
        public readonly Party $party,
        public readonly PartyMember $member,
        public readonly Track $track,
        public readonly string $driver,
        public readonly ?string $secret = null,
    ) {}

    public function usage(?int $promptTokens, ?int $completionTokens, ?int $totalTokens = null): void
    {
        $this->promptTokens = $promptTokens;
        $this->completionTokens = $completionTokens;
        $this->totalTokens = $totalTokens ?? ($promptTokens === null && $completionTokens === null ? null : (int) $promptTokens + (int) $completionTokens);
    }

    /**
     * @param  array<mixed>|null  $usage  An OpenAI or Decision API style usage object
     */
    public function usageFrom(?array $usage): void
    {
        if ($usage === null) {
            return;
        }
        $this->usage(
            self::count($usage, 'prompt_tokens', 'input_tokens'),
            self::count($usage, 'completion_tokens', 'output_tokens'),
            self::count($usage, 'total_tokens'),
        );
    }

    /**
     * @param  array<mixed>  $usage
     */
    private static function count(array $usage, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                return (int) $usage[$key];
            }
        }

        return null;
    }
}
