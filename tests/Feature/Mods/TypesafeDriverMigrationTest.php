<?php

use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use Illuminate\Support\Facades\Artisan;

function typesafeMigration(): object
{
    return require database_path('migrations/2026_11_26_110000_move_typesafe_ai_review_parties_to_decision_model.php');
}

function storeRawAiSetting(Party $party, string $code, string $value): void
{
    $modId = Mod::whereCode(AiRequestReviewMod::CODE)->value('id');
    $setting = ModSetting::whereMod_id($modId)->whereCode($code)->firstOrFail();
    PartyModSetting::query()->insert(['party_id' => $party->id, 'mod_setting_id' => $setting->id, 'value' => serialize($value), 'created_at' => now(), 'updated_at' => now()]);
}

function aiReviewValue(Party $party, string $code): mixed
{
    app(ModSettingsStore::class)->forget($party);

    return app(ModSettingsStore::class)->value($party->fresh(), AiRequestReviewMod::CODE, $code);
}

beforeEach(function () {
    Artisan::call('mods:sync');
});

it('moves typesafe parties to the decision model driver pointing at direct TypeSafe', function () {
    $party = Party::factory()->create();
    storeRawAiSetting($party, 'driver', 'typesafe');
    storeRawAiSetting($party, 'model', 'jev-1.2');
    app(PartyMods::class)->enable($party, AiRequestReviewMod::CODE);

    typesafeMigration()->up();

    expect(aiReviewValue($party, 'driver'))->toBe('decision-model')
        ->and(aiReviewValue($party, 'decision_endpoint'))->toBe('https://api.typesafe.ai/v1/systemone')
        ->and(aiReviewValue($party, 'decision_model'))->toBe('jev-1.2');
});

it('keeps the api key and falls back to jev-latest without a model', function () {
    $party = Party::factory()->create();
    storeRawAiSetting($party, 'driver', 'typesafe');
    app(ModSettingsStore::class)->set($party, AiRequestReviewMod::CODE, 'api_key', 'sk-keep-me');

    typesafeMigration()->up();

    expect(aiReviewValue($party, 'decision_model'))->toBe('jev-latest')
        ->and(aiReviewValue($party, 'api_key'))->toBe('sk-keep-me');
});

it('treats an enabled party with no stored driver as typesafe because that was the default', function () {
    $party = Party::factory()->create();
    app(PartyMods::class)->enable($party, AiRequestReviewMod::CODE);

    typesafeMigration()->up();

    expect(aiReviewValue($party, 'driver'))->toBe('decision-model')
        ->and(aiReviewValue($party, 'decision_endpoint'))->toBe('https://api.typesafe.ai/v1/systemone');
});

it('leaves other drivers and disabled parties alone', function () {
    $chat = Party::factory()->create();
    storeRawAiSetting($chat, 'driver', 'openai-compatible');
    storeRawAiSetting($chat, 'decision_endpoint', 'https://example.test/decisions');
    $disabled = Party::factory()->create();

    typesafeMigration()->up();

    expect(aiReviewValue($chat, 'driver'))->toBe('openai-compatible')
        ->and(aiReviewValue($chat, 'decision_endpoint'))->toBe('https://example.test/decisions')
        ->and(PartyModSetting::query()->where('party_id', $disabled->id)->exists())->toBeFalse();
});
