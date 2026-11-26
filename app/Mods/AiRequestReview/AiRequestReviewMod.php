<?php

namespace App\Mods\AiRequestReview;

use App\Enums\SettingType;
use App\Models\Party;
use App\Mods\Contracts\ContributesRequestRules;
use App\Mods\Contracts\Mod;
use App\Mods\SettingDefinition;

class AiRequestReviewMod implements ContributesRequestRules, Mod
{
    public const CODE = 'ai-request-review';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'AI Request Review';
    }

    public function description(): string
    {
        return 'Asks an AI model to review each new Request, allowing it, flagging it for managers or rejecting it with a reason.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('driver', 'Driver', SettingType::stChoice, AiRequestReviewRule::DRIVER_DECISION_MODEL, 'A decision model (Jev) scores the request through a decisions endpoint such as OpenRouter or direct TypeSafe; OpenAI-compatible uses a chat model.', options: [AiRequestReviewRule::DRIVER_DECISION_MODEL => 'Decision model (Jev / TypeSafe)', AiRequestReviewRule::DRIVER_OPENAI_COMPATIBLE => 'OpenAI-compatible']),
            new SettingDefinition('endpoint', 'Endpoint', SettingType::stString, null, 'Base URL of the OpenAI-compatible API. Not used by the decision model driver.', validation: ['url']),
            new SettingDefinition('api_key', 'API Key', SettingType::stSecret, null, 'Used by every driver. Stored encrypted and never shown again.'),
            new SettingDefinition('model', 'Model', SettingType::stString),
            new SettingDefinition('decision_endpoint', 'Decision endpoint', SettingType::stString, DecisionModelReviewer::OPENROUTER_ENDPOINT, 'Decision model driver only. An https URL; direct TypeSafe is https://api.typesafe.ai/v1/systemone.', validation: ['url:https']),
            new SettingDefinition('decision_model', 'Decision model', SettingType::stString, DecisionModelReviewer::DEFAULT_MODEL, 'Decision model driver only. Use jev-latest for direct TypeSafe.'),
            new SettingDefinition('confidence_threshold', 'Confidence threshold', SettingType::stFloat, DecisionModelReviewer::DEFAULT_CONFIDENCE_THRESHOLD, 'Decision model driver only. Answers below this confidence are flagged for a manager.', validation: ['min:0', 'max:1']),
            new SettingDefinition('include_party_context', 'Include party context', SettingType::stBoolean, true, 'Sends the model a short music-only summary of this Party: recent and best-rated tracks, top artists and a few stats. No names or user data.'),
            new SettingDefinition('timeout', 'Timeout (seconds)', SettingType::stInteger, 10, validation: ['min:1', 'max:60']),
            new SettingDefinition('guidelines', 'Guidelines', SettingType::stString, null, 'What this Party does and does not want to hear, passed to the model.'),
        ];
    }

    public function extensionPoints(): array
    {
        return ['request-rules'];
    }

    public function modRequestRules(): array
    {
        return [];
    }

    public function aiRequestRules(): array
    {
        return [app(AiRequestReviewRule::class)];
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void {}
}
