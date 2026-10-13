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
            new SettingDefinition('driver', 'Driver', SettingType::stString, AiRequestReviewRule::DRIVER_TYPESAFE, 'Either "typesafe" (Jev) or "openai-compatible".', validation: ['in:typesafe,openai-compatible'], instanceOnly: true),
            new SettingDefinition('endpoint', 'Endpoint', SettingType::stString, null, 'Base URL of the OpenAI-compatible API. Not needed for Jev.', validation: ['url'], instanceOnly: true),
            new SettingDefinition('api_key', 'API Key', SettingType::stSecret, null, instanceOnly: true),
            new SettingDefinition('model', 'Model', SettingType::stString, null, instanceOnly: true),
            new SettingDefinition('timeout', 'Timeout (seconds)', SettingType::stInteger, 10, validation: ['min:1', 'max:60'], instanceOnly: true),
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

    public function onEnable(Party $party): void {}

    public function onDisable(Party $party): void {}
}
