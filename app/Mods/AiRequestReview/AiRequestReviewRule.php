<?php

namespace App\Mods\AiRequestReview;

use App\Models\Party;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Track;
use App\Services\Requests\RequestContext;
use App\Services\Requests\RequestDecision;
use App\Services\Requests\RequestMode;
use App\Services\Requests\RequestRule;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Throwable;

class AiRequestReviewRule implements RequestRule
{
    public const DRIVER_OPENAI_COMPATIBLE = 'openai-compatible';

    public const DRIVER_DECISION_MODEL = 'decision-model';

    public function __construct(protected ModSettingsStore $settings, protected PartyMods $partyMods, protected DecisionModelReviewer $decisionModel, protected AiReviewLog $log, protected PartyVibeSummary $vibe, protected AiReviewRecorder $recorder) {}

    public function decide(RequestContext $context, Track $track): RequestDecision
    {
        if ($context->mode !== RequestMode::Submit || ! $this->partyMods->isEnabled($context->party, AiRequestReviewMod::CODE)) {
            return RequestDecision::allow();
        }

        $driver = $this->setting($context->party, 'driver');
        $driver = is_string($driver) && $driver !== '' ? $driver : self::DRIVER_DECISION_MODEL;
        $log = [
            'party_id' => $context->party->id,
            'party_code' => $context->party->code,
            'track_provider' => $track->providerId,
            'track_id' => $track->id,
            'driver' => $driver,
        ];
        $startedAt = hrtime(true);
        $trace = new AiReviewTrace($context->party, $context->member, $track, $driver, $this->apiKeyOf($context->party));
        $this->log->debug('ai-review.start', $log);

        if ($driver === self::DRIVER_DECISION_MODEL) {
            return $this->decideWithDecisionModel($context->party, $track, $log, $startedAt, $trace);
        }

        $model = $this->setting($context->party, 'model');
        if (! is_string($model) || $model === '') {
            return $this->logged(RequestDecision::allow(), $log, $startedAt, 'not_configured');
        }

        try {
            $guidelines = $this->settings->value($context->party, AiRequestReviewMod::CODE, 'guidelines');
            $guidelines = is_string($guidelines) ? $guidelines : null;
            $timeout = max(1, (int) ($this->setting($context->party, 'timeout') ?? 10));
            $provider = $this->providerName($context->party);
            $partyContext = $this->partyContext($context->party);
            $log += ['provider' => $provider, 'model' => $model, 'timeout' => $timeout, 'party_context_included' => $partyContext !== null];
            $trace->model = $model;
            $endpoint = $this->setting($context->party, 'endpoint');
            $trace->endpointHost = is_string($endpoint) ? (parse_url($endpoint, PHP_URL_HOST) ?: null) : null;
            [$verdict, $reason] = $this->prompt($track, $guidelines, $provider, $model, $timeout, $partyContext, $log, $trace);

            return $this->logged($this->interpret($verdict, $reason), $log, $startedAt, is_string($verdict) ? "verdict:{$verdict}" : 'no_verdict', $trace);
        } catch (Throwable $e) {
            Log::warning("AI Request Review unavailable: {$e->getMessage()}");

            return $this->failedOpen($e, $log, $startedAt, $trace);
        }
    }

    /**
     * @param  array<string, mixed>  $log
     */
    protected function logged(RequestDecision $decision, array $log, int $startedAt, string $why, ?AiReviewTrace $trace = null): RequestDecision
    {
        $latency = $this->log->elapsedMs($startedAt);
        $this->log->debug('ai-review.outcome', $log + [
            'outcome' => $decision->outcome->value,
            'reason' => $decision->reason,
            'why' => $why,
            'latency_ms' => $latency,
        ]);
        if ($trace !== null) {
            $this->recorder->record($trace, $decision, $latency);
        }

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    protected function failedOpen(Throwable $e, array $log, int $startedAt, AiReviewTrace $trace): RequestDecision
    {
        $this->log->failure($e, $trace->secret, $log + ['latency_ms' => $this->log->elapsedMs($startedAt)]);
        $trace->errorClass = $e::class;
        $trace->errorMessage = $this->log->redact($e->getMessage(), $trace->secret);

        return $this->logged(RequestDecision::allow(), $log, $startedAt, 'fail_open_on_error', $trace);
    }

    protected function apiKeyOf(Party $party): ?string
    {
        $apiKey = $this->setting($party, 'api_key');

        return is_string($apiKey) ? $apiKey : null;
    }

    /**
     * @param  array<string, mixed>  $log
     */
    protected function decideWithDecisionModel(Party $party, Track $track, array $log, int $startedAt, AiReviewTrace $trace): RequestDecision
    {
        $apiKey = $this->setting($party, 'api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return $this->logged(RequestDecision::allow(), $log, $startedAt, 'not_configured');
        }

        try {
            $guidelines = $this->setting($party, 'guidelines');
            $endpoint = $this->setting($party, 'decision_endpoint');
            $model = $this->setting($party, 'decision_model');
            $percentage = $this->setting($party, 'confidence_percentage');

            $partyState = $this->partyState($party);
            $log['party_context_included'] = $partyState !== null;
            $decision = $this->decisionModel->review(
                is_string($endpoint) && $endpoint !== '' ? $endpoint : DecisionModelReviewer::OPENROUTER_ENDPOINT,
                $apiKey,
                is_string($model) && $model !== '' ? $model : DecisionModelReviewer::DEFAULT_MODEL,
                is_string($guidelines) ? $guidelines : null,
                ['request' => $this->trackDetails($track)] + ($partyState !== null ? ['party' => $partyState] : []),
                max(1, (int) ($this->setting($party, 'timeout') ?? 10)),
                (is_numeric($percentage) ? (float) $percentage : DecisionModelReviewer::DEFAULT_CONFIDENCE_PERCENTAGE) / 100,
                $log,
                $trace,
            );

            return $this->logged($decision, $log, $startedAt, 'decision_model', $trace);
        } catch (Throwable $e) {
            Log::warning('AI Request Review unavailable: '.($e instanceof DecisionModelException ? $e->getMessage() : $e::class));

            return $this->failedOpen($e, $log, $startedAt, $trace);
        }
    }

    /**
     * @param  array<string, mixed>  $log
     * @return array{0: mixed, 1: mixed}
     */
    protected function prompt(Track $track, ?string $guidelines, string $provider, string $model, int $timeout, ?string $partyContext, array $log, AiReviewTrace $trace): array
    {
        $agent = new RequestReviewAgent($guidelines);
        $message = $this->describe($track, $partyContext);
        $system = (string) $agent->instructions();
        $this->log->debug('ai-review.request', $log + ['system' => $system, 'user' => $message]);
        $trace->request = ['model' => $model, 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $message]]];
        $startedAt = hrtime(true);

        $response = $agent->prompt($message, provider: $provider, model: $model, timeout: $timeout);

        $this->log->debug('ai-review.response', $log + [
            'latency_ms' => $this->log->elapsedMs($startedAt),
            'verdict' => $response['verdict'] ?? null,
            'reason' => $response['reason'] ?? null,
        ]);
        $trace->response = ['verdict' => $response['verdict'] ?? null, 'reason' => $response['reason'] ?? null];
        if ($response->usage->totalTokens() > 0) {
            $trace->usage($response->usage->inputTokens, $response->usage->outputTokens, $response->usage->totalTokens());
        }

        return [$response['verdict'] ?? null, $response['reason'] ?? null];
    }

    protected function interpret(mixed $verdict, mixed $reason): RequestDecision
    {
        $reason = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;

        return match ($verdict) {
            'reject' => RequestDecision::reject($reason ?? 'This song was rejected by the AI review'),
            'flag' => RequestDecision::flag($reason ?? 'Flagged by the AI review'),
            default => RequestDecision::allow(),
        };
    }

    protected function providerName(Party $party): string
    {
        $config = ['driver' => self::DRIVER_OPENAI_COMPATIBLE, 'key' => $this->setting($party, 'api_key')];
        $endpoint = $this->setting($party, 'endpoint');
        if (is_string($endpoint) && $endpoint !== '') {
            $config['url'] = $endpoint;
        }

        return Ai::build($config)->name();
    }

    protected function setting(Party $party, string $code): mixed
    {
        return $this->settings->value($party, AiRequestReviewMod::CODE, $code);
    }

    protected function describe(Track $track, ?string $partyContext = null): string
    {
        return json_encode($this->trackDetails($track, $partyContext), JSON_THROW_ON_ERROR);
    }

    protected function partyContext(Party $party): ?string
    {
        $include = $this->setting($party, 'include_party_context');
        if ($include === false || $include === '0' || $include === 0) {
            return null;
        }
        $text = $this->vibe->text($party);

        return $text === '' ? null : $text;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function partyState(Party $party): ?array
    {
        if ($this->partyContext($party) === null) {
            return null;
        }
        $data = $this->vibe->data($party);

        return [
            'stats' => $data['stats'],
            'recent' => $data['recent'],
            'top_rated' => $data['top_rated'],
            'downvoted' => $data['downvoted'],
            'most_upvoted_requests' => $data['most_upvoted_requests'],
            'most_downvoted_requests' => $data['most_downvoted_requests'],
            'artists' => $data['artists'],
        ];
    }

    /**
     * @return array{title: string, artists: string, album: string, explicit: bool, party_context?: string}
     */
    protected function trackDetails(Track $track, ?string $partyContext = null): array
    {
        return [
            'title' => $track->name,
            'artists' => implode(', ', array_map(fn (ArtistRef $artist): string => $artist->name, $track->artists)),
            'album' => $track->album->name,
            'explicit' => $track->explicit,
        ] + ($partyContext !== null ? ['party_context' => $partyContext] : []);
    }
}
