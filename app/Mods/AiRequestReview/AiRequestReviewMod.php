<?php

namespace App\Mods\AiRequestReview;

use App\Enums\SettingType;
use App\Models\Party;
use App\Mods\Contracts\ContributesRequestRules;
use App\Mods\Contracts\Mod;
use App\Mods\Contracts\ValidatesSettings;
use App\Mods\SettingDefinition;
use App\Services\ModSettingsStore;

class AiRequestReviewMod implements ContributesRequestRules, Mod, ValidatesSettings
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
            new SettingDefinition('endpoint', 'Endpoint', SettingType::stString, null, 'Base URL of the OpenAI-compatible API. Not used by the decision model driver. Must be a public address; private, loopback or docker-network hosts need Allow internal endpoints.', validation: ['url']),
            new SettingDefinition('api_key', 'API Key', SettingType::stSecret, null, 'Used by every driver. Stored encrypted and never shown again.'),
            new SettingDefinition('model', 'Model', SettingType::stString),
            new SettingDefinition('decision_endpoint', 'Decision endpoint', SettingType::stString, DecisionModelReviewer::OPENROUTER_ENDPOINT, 'Decision model driver only. An http or https URL; direct TypeSafe is https://api.typesafe.ai/v1/systemone. Checked when saved.', validation: ['url']),
            new SettingDefinition('decision_model', 'Decision model', SettingType::stString, DecisionModelReviewer::DEFAULT_MODEL, 'Decision model driver only. Use jev-latest for direct TypeSafe.'),
            new SettingDefinition('confidence_percentage', 'Confidence threshold (%)', SettingType::stInteger, DecisionModelReviewer::DEFAULT_CONFIDENCE_PERCENTAGE, 'Decision model driver only. When the model is at least this confident, its rating is acted on; below it, the request is allowed.', validation: ['min:0', 'max:100']),
            new SettingDefinition('include_party_context', 'Include party context', SettingType::stBoolean, true, 'Sends the model a short music-only summary of this Party: recent, best-rated and most downvoted tracks, the most upvoted and downvoted requests, top artists and a few stats. No names or user data.'),
            new SettingDefinition('timeout', 'Timeout (seconds)', SettingType::stInteger, 10, 'Capped at 20 seconds.', validation: ['min:1', 'max:20']),
            new SettingDefinition('guidelines', 'Guidelines', SettingType::stText, null, 'What this Party does and does not want to hear, passed to the model.'),
            new SettingDefinition('allow_internal_endpoints', 'Allow internal endpoints', SettingType::stBoolean, false, 'Site admins only. Lets endpoints on private, loopback or link-local addresses be used, for self-hosted models. Off by default. Without it a LAN IP or docker service name (such as http://ollama:11434/v1) is refused when saving and the admin Mods page shows the last failure.', instanceOnly: true),
        ];
    }

    public function validateSettings(array $submitted): array
    {
        $guard = app(EndpointGuard::class);
        $allowInternal = app(ModSettingsStore::class)->instanceValue(self::CODE, 'allow_internal_endpoints') === true;
        $errors = [];
        foreach (['endpoint', 'decision_endpoint'] as $code) {
            $value = $submitted[$code] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }
            try {
                $guard->assertSafe($value, $allowInternal);
            } catch (DecisionModelException $e) {
                $errors[$code] = $e->getMessage().($allowInternal ? '.' : '. Self-hosted endpoints on private addresses need a site admin to enable Allow internal endpoints.');
            }
        }

        return $errors;
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
