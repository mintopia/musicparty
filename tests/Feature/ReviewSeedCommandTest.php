<?php

use App\Models\AiReview;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\Dashboard\SystemHealth;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Review\ReviewHarnessSeeder;
use Illuminate\Support\Facades\Cache;

it('seeds the review states', function () {
    $this->artisan('review:seed')->assertSuccessful();

    $stratlan = Theme::where('name', 'StratLAN')->firstOrFail();
    expect($stratlan->active)->toBeTrue()
        ->and($stratlan->dark_mode)->toBeTrue()
        ->and($stratlan->tokens)->toHaveCount(7)
        ->and($stratlan->css)->not->toBe('')
        ->and(Theme::where('name', 'Reference Theme')->exists())->toBeTrue()
        ->and(Theme::where('readonly', false)->where('active', false)->count())->toBeGreaterThanOrEqual(2);

    $discord = SocialProvider::whereCode('discord')->firstOrFail();
    $spotify = SocialProvider::whereCode('spotify')->firstOrFail();
    $steam = SocialProvider::whereCode('steam')->firstOrFail();
    expect([$discord->enabled, $discord->auth_enabled])->toBe([true, true])
        ->and([$spotify->enabled, $spotify->auth_enabled])->toBe([true, false])
        ->and([$steam->enabled, $steam->auth_enabled])->toBe([true, true])
        ->and(trim((string) $steam->getSetting('client_secret')))->toBe('');

    $relink = User::where('nickname', ReviewHarnessSeeder::RELINK_NICKNAME)->firstOrFail();
    expect($relink->accounts()->whereNotNull('needs_relink_at')->count())->toBe(1)
        ->and(Party::where('code', ReviewHarnessSeeder::PARTY_RELINK)->firstOrFail()->user_id)->toBe($relink->id);
    expect(User::where('nickname', ReviewHarnessSeeder::TWO_LINKS_NICKNAME)->firstOrFail()->accounts()->count())->toBe(2)
        ->and(User::where('nickname', ReviewHarnessSeeder::BOTH_SPOTIFY_NICKNAME)->firstOrFail()->accounts()->where('social_provider_id', $spotify->id)->count())->toBe(2);

    $flagOff = Party::where('code', ReviewHarnessSeeder::PARTY_FLAG_OFF)->firstOrFail();
    $empty = Party::where('code', ReviewHarnessSeeder::PARTY_NO_REVIEWS)->firstOrFail();
    $answers = Party::where('code', ReviewHarnessSeeder::PARTY_WITH_ANSWERS)->firstOrFail();
    $noMod = Party::where('code', ReviewHarnessSeeder::PARTY_NO_AI_MOD)->firstOrFail();
    $mods = app(PartyMods::class);
    expect($flagOff->flag_requests)->toBeFalse()
        ->and(AiReview::where('party_id', $empty->id)->count())->toBe(0)
        ->and(AiReview::where('party_id', $answers->id)->whereNotNull('criterion')->count())->toBeGreaterThan(0)
        ->and($mods->isEnabled($empty, AiRequestReviewMod::CODE))->toBeTrue()
        ->and($mods->isEnabled($noMod, AiRequestReviewMod::CODE))->toBeFalse()
        ->and(Cache::get(SystemHealth::SCHEDULER_HEARTBEAT_KEY))->toBeInt();
});

it('is idempotent', function () {
    $this->artisan('review:seed')->assertSuccessful();
    $counts = fn (): array => [Theme::count(), User::count(), LinkedAccount::count(), Party::count(), AiReview::count(), SocialProvider::count()];
    $first = $counts();

    $this->artisan('review:seed')->assertSuccessful();

    expect($counts())->toBe($first)
        ->and(Theme::where('active', true)->count())->toBe(1);
});

it('can make every party inactive for the quiet dashboard', function () {
    $this->artisan('review:seed --quiet-parties')->assertSuccessful();

    expect(Party::where('active', true)->count())->toBe(0);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('review:seed')->assertFailed();

    expect(Theme::where('name', 'StratLAN')->exists())->toBeFalse();
});

it('is not part of the default seeder', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Theme::where('name', 'StratLAN')->exists())->toBeFalse();
});
