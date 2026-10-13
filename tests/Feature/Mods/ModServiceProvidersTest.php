<?php

use App\Models\Party;
use App\Mods\AiRequestReview\AiRequestReviewRule;
use App\Mods\AiRequestReview\AiRequestReviewServiceProvider;
use App\Mods\ModRegistry;
use App\Mods\Whamageddon\Whamageddon;
use App\Mods\Whamageddon\WhamageddonServiceProvider;

it('discovers a service provider for Whamageddon and AI Request Review', function () {
    expect(app(ModRegistry::class)->providers())->toContain(WhamageddonServiceProvider::class, AiRequestReviewServiceProvider::class);
});

it('resolves Whamageddon for a party through its provider', function () {
    $party = Party::factory()->create();

    expect(app(Whamageddon::class, ['party' => $party]))->toBeInstanceOf(Whamageddon::class);
});

it('scopes the AI review rule to the request lifecycle', function () {
    expect(app(AiRequestReviewRule::class))->toBe(app(AiRequestReviewRule::class));
    expect(app()->isShared(AiRequestReviewRule::class))->toBeTrue();
});
