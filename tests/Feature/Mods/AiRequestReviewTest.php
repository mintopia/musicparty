<?php

use App\Models\Mod;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\AiRequestReview\RequestReviewAgent;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\RequestMode;
use App\Services\Requests\RequestOutcome;
use App\Services\Requests\RequestRules;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

function aiReviewTrack(): Track
{
    return app(FakeMusicProvider::class)->getTrack('fake-track-1') ?? throw new RuntimeException('No fake track');
}

function aiReviewSetup(bool $flagRequests = true, bool $enabled = true, string $driver = 'openai-compatible'): array
{
    Artisan::call('mods:sync');
    $party = Party::factory()->create(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true, 'flag_requests' => $flagRequests])->refresh();
    $member = PartyMember::factory()->for($party)->for(User::factory())->create();
    $store = app(ModSettingsStore::class);
    $store->setInstance(AiRequestReviewMod::CODE, 'driver', $driver);
    $store->setInstance(AiRequestReviewMod::CODE, 'endpoint', 'https://llm.example.test/v1');
    $store->setInstance(AiRequestReviewMod::CODE, 'model', 'review-1');
    $store->setInstance(AiRequestReviewMod::CODE, 'api_key', 'secret');
    if ($enabled) {
        app(PartyMods::class)->enable($party, AiRequestReviewMod::CODE);
    }

    return [$party->refresh(), $member];
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('allows when the model allows', function () {
    RequestReviewAgent::fake([['verdict' => 'allow', 'reason' => 'Fine']])->preventStrayPrompts();
    [$party, $member] = aiReviewSetup();

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->outcome)->toBe(RequestOutcome::Allow);
    RequestReviewAgent::assertPromptedTimes(1);
});

it('flags with the reason when flagging is on', function () {
    RequestReviewAgent::fake([['verdict' => 'flag', 'reason' => 'Borderline lyrics']]);
    [$party, $member] = aiReviewSetup(flagRequests: true);

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->isFlagged())->toBeTrue()->and($decision->reason)->toBe('Borderline lyrics');
});

it('allows a flagged request when flagging is off', function () {
    RequestReviewAgent::fake([['verdict' => 'flag', 'reason' => 'Borderline lyrics']]);
    [$party, $member] = aiReviewSetup(flagRequests: false);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
});

it('rejects with the reason shown to the requester', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'Not suitable for this party']]);
    [$party, $member] = aiReviewSetup(flagRequests: false);

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

    expect($decision->allowed)->toBeFalse()->and($decision->reason)->toBe('Not suitable for this party');
});

it('allows when the model errors or answers nonsense', function (Closure|array $response) {
    RequestReviewAgent::fake($response);
    [$party, $member] = aiReviewSetup();

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
})->with([
    'error' => fn () => fn () => throw new RuntimeException('timeout'),
    'unparseable' => [['I cannot decide']],
]);

it('allows without calling the model when it is not configured', function () {
    RequestReviewAgent::fake()->preventStrayPrompts();
    [$party, $member] = aiReviewSetup();
    app(ModSettingsStore::class)->setInstance(AiRequestReviewMod::CODE, 'model', null);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
    RequestReviewAgent::assertNeverPrompted();
});

it('does not call the model for a party that has not enabled the mod', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'No']]);
    [$party, $member] = aiReviewSetup(enabled: false);

    expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->allowed)->toBeTrue();
    RequestReviewAgent::assertNeverPrompted();
});

it('does not call the model during annotation', function () {
    RequestReviewAgent::fake([['verdict' => 'reject', 'reason' => 'No']]);
    [$party, $member] = aiReviewSetup();

    $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack(), RequestMode::Annotate);

    expect($decision->allowed)->toBeTrue();
    RequestReviewAgent::assertNeverPrompted();
});

it('registers the instance-only secret settings', function () {
    Artisan::call('mods:sync');
    $mod = Mod::whereCode(AiRequestReviewMod::CODE)->firstOrFail();

    expect($mod->settings()->whereCode('api_key')->sole())->encrypted->toBeTrue()->instance_only->toBeTrue();
});

describe('Jev classification', function () {
    it('maps the classified verdict to a decision', function (string $choice, bool $flagRequests, RequestOutcome $expected) {
        Classification::fake([['verdict' => new ChoiceAnswer($choice, [$choice => 1.0])]]);
        [$party, $member] = aiReviewSetup(flagRequests: $flagRequests, driver: 'typesafe');

        $decision = app(RequestRules::class)->check($party, $member, aiReviewTrack());

        expect($decision->outcome)->toBe($expected);
        Classification::assertClassified(fn () => true);
    })->with([
        'allow' => ['allow', true, RequestOutcome::Allow],
        'flag on' => ['flag', true, RequestOutcome::Flag],
        'flag off' => ['flag', false, RequestOutcome::Allow],
        'reject' => ['reject', false, RequestOutcome::Reject],
    ]);

    it('allows when classification fails', function () {
        Classification::fake(fn () => throw new RuntimeException('timeout'));
        [$party, $member] = aiReviewSetup(driver: 'typesafe');

        expect(app(RequestRules::class)->check($party, $member, aiReviewTrack())->outcome)->toBe(RequestOutcome::Allow);
    });

    it('does not classify during annotation', function () {
        Classification::fake();
        [$party, $member] = aiReviewSetup(driver: 'typesafe');

        app(RequestRules::class)->check($party, $member, aiReviewTrack(), RequestMode::Annotate);

        Classification::assertNothingClassified();
    });
});
