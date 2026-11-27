<?php

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

beforeEach(function () {
    $this->withoutVite();
    Artisan::call('mods:sync');
    $this->owner = User::factory()->create(['first_login' => false]);
    $this->party = Party::factory()->create(['user_id' => $this->owner->id, 'provider_id' => 'fake']);
    app(PartyMods::class)->enable($this->party, AiRequestReviewMod::CODE);
});

it('forbids non-managers', function () {
    $review = AiReview::factory()->for($this->party)->create();

    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('parties.ai-reviews.index', $this->party->code))->assertForbidden();
    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('parties.ai-reviews.show', [$this->party->code, $review]))->assertForbidden();
});

it('sends guests to login', function () {
    $this->get(route('parties.ai-reviews.index', $this->party->code))->assertRedirect();
});

it('forbids a plain member of the party', function () {
    $member = User::factory()->create(['first_login' => false]);
    PartyMember::factory()->for($this->party)->for($member)->create();

    $this->actingAs($member)->get(route('parties.ai-reviews.index', $this->party->code))->assertForbidden();
});

it('returns 404 when the mod is not enabled for the party', function () {
    $review = AiReview::factory()->for($this->party)->create();
    app(PartyMods::class)->disable($this->party, AiRequestReviewMod::CODE);

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))->assertNotFound();
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.show', [$this->party->code, $review]))->assertNotFound();
});

it('renders the list for a manager newest first with the requester and tokens', function () {
    $requester = User::factory()->create(['nickname' => 'Dana']);
    AiReview::factory()->for($this->party)->create(['track_title' => 'Older']);
    AiReview::factory()->for($this->party)->for($requester)->outcome(AiReviewOutcome::Reject)->create(['track_title' => 'Newer', 'reason' => 'Too loud', 'total_tokens' => 99, 'latency_ms' => 812]);

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/AiReviews/Index')
            ->where('party.code', $this->party->code)
            ->has('playerState')
            ->has('outcomes', 4)
            ->has('reviews.data', 2)
            ->where('reviews.data.0.track.title', 'Newer')
            ->where('reviews.data.0.user', 'Dana')
            ->where('reviews.data.0.outcome', 'reject')
            ->where('reviews.data.0.reason', 'Too loud')
            ->where('reviews.data.0.totalTokens', 99)
            ->where('reviews.data.0.latencyMs', 812)
            ->where('reviews.data.1.track.title', 'Older'));
});

it('only lists reviews for this party', function () {
    AiReview::factory()->for($this->party)->create();
    AiReview::factory()->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1));
});

it('filters by outcome', function () {
    AiReview::factory()->for($this->party)->count(2)->create();
    AiReview::factory()->for($this->party)->outcome(AiReviewOutcome::Flag)->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'outcome' => 'flag']))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('reviews.data.0.outcome', 'flag')->where('filters.outcome', 'flag'));
});

it('filters by criterion and exposes it on each row', function () {
    AiReview::factory()->for($this->party)->create(['criterion' => AiReviewCriterion::Vibe, 'criterion_confidence' => 0.7]);
    AiReview::factory()->for($this->party)->create(['criterion' => AiReviewCriterion::Troll]);
    AiReview::factory()->for($this->party)->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'criterion' => 'vibe']))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)
            ->where('reviews.data.0.criterion', 'vibe')
            ->where('reviews.data.0.criterionLabel', 'Vibe')
            ->where('reviews.data.0.criterionConfidence', 0.7)
            ->where('filters.criterion', 'vibe'));
});

it('rejects an unknown criterion filter', function () {
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'criterion' => 'bogus']))->assertSessionHasErrors('criterion');
});

it('rejects an unknown outcome filter', function () {
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'outcome' => 'bogus']))->assertSessionHasErrors('outcome');
});

it('searches by track title, artist and requester nickname', function (string $term, string $expected) {
    AiReview::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Zed']))->create(['track_title' => 'Mr Brightside', 'track_artists' => 'The Killers']);
    AiReview::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Quinn']))->create(['track_title' => 'Last Christmas', 'track_artists' => 'Wham!']);

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'q' => $term]))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('reviews.data.0.track.title', $expected));
})->with([
    'title' => ['bright', 'Mr Brightside'],
    'artist' => ['wham', 'Last Christmas'],
    'nickname' => ['quinn', 'Last Christmas'],
]);

it('treats like wildcards in the search as literal text', function () {
    AiReview::factory()->for($this->party)->create(['track_title' => 'Plain']);

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 0));
});

it('paginates twenty at a time and keeps the filters in the links', function () {
    AiReview::factory()->for($this->party)->count(25)->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'outcome' => 'allow']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 20)
            ->where('reviews.last_page', 2)
            ->where('reviews.next_page_url', fn (string $url): bool => str_contains($url, 'outcome=allow') && str_contains($url, 'page=2')));
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', [$this->party->code, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 5));
});

it('renders the review as its own page with the request, response and error', function () {
    $review = AiReview::factory()->for($this->party)->outcome(AiReviewOutcome::Error)->create([
        'request' => ['model' => 'jev-latest', 'state' => json_encode(['title' => 'x', 'party' => ['artists' => [['name' => 'Wham!', 'plays' => 2]]], 'party_context' => 'Tracks played: 3']), 'questions' => []],
        'response' => ['status' => 500, 'body' => 'boom'],
        'score' => null,
        'error_class' => 'RuntimeException',
        'error_message' => 'It broke',
    ]);

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.show', [$this->party->code, $review]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/AiReviews/Show')
            ->where('review.id', $review->id)
            ->missing('review.partyContext')
            ->missing('review.partyState')
            ->where('review.request.model', 'jev-latest')
            ->where('review.response.status', 500)
            ->where('review.errorClass', 'RuntimeException')
            ->where('review.errorMessage', 'It broke')
            ->where('review.endpointHost', 'api.typesafe.ai')
            ->where('backUrl', route('parties.ai-reviews.index', $this->party->code))
            ->has('moderationTabs'));
});

it('links back to the list with the filters it was opened from', function () {
    $review = AiReview::factory()->for($this->party)->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.show', [$this->party->code, $review, 'outcome' => 'flag', 'q' => 'wham', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('backUrl', route('parties.ai-reviews.index', [$this->party->code, 'outcome' => 'flag', 'q' => 'wham', 'page' => 2])));
});

it('does not show a review from another party under this party', function () {
    $other = AiReview::factory()->create();

    $this->actingAs($this->owner)->get(route('parties.ai-reviews.show', [$this->party->code, $other]))->assertNotFound();
});

it('shares the nav flag only for managers of a party with the mod enabled', function () {
    $this->actingAs($this->owner);
    PartyMember::factory()->owner()->for($this->party)->for($this->owner)->create();

    $this->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('auth.parties.0.aiReviewEnabled', true)->where('moderationTabs.aiReviewEnabled', true)->where('auth.parties.0.flaggedCount', 0));

    app(PartyMods::class)->disable($this->party, AiRequestReviewMod::CODE);
    $this->get(route('parties.edit', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('auth.parties.0.aiReviewEnabled', false));
});

describe('linking to the request', function () {
    beforeEach(function () {
        app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));
        $store = app(ModSettingsStore::class);
        $store->set($this->party, AiRequestReviewMod::CODE, 'driver', 'decision-model');
        $store->set($this->party, AiRequestReviewMod::CODE, 'api_key', 'sk-secret-key');
        $this->party->forceFill(['active' => true, 'allow_requests' => true, 'flag_requests' => true])->save();
        $this->member = PartyMember::factory()->for($this->party)->for(User::factory()->create(['first_login' => false]))->create();
        Sanctum::actingAs($this->member->user);
    });

    function reviewAnswer(float $score): array
    {
        return ['answers' => ['suitability' => ['type' => 'score', 'score' => $score, 'legend' => [0 => 'No', 1 => 'Hmm', 2 => 'Unsure', 3 => 'Ok', 4 => 'Yes'], 'probabilities' => [], 'confidence' => 0.9]]];
    }

    it('links the review to the request that was created', function () {
        Http::fake(['*' => Http::response(reviewAnswer(4.0))]);

        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1'])->assertSuccessful();

        expect(AiReview::sole()->upcoming_song_id)->toBe(UpcomingSong::sole()->id);
    });

    it('links the review to a flagged request', function () {
        Http::fake(['*' => Http::response(reviewAnswer(1.4))]);

        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1'])->assertStatus(202);

        $review = AiReview::sole();
        expect($review->outcome)->toBe(AiReviewOutcome::Flag)->and($review->upcoming_song_id)->toBe(UpcomingSong::sole()->id);
    });

    it('leaves a rejected review unlinked because no request exists', function () {
        Http::fake(['*' => Http::response(reviewAnswer(0.1))]);

        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1'])->assertStatus(400);

        expect(AiReview::sole()->upcoming_song_id)->toBeNull()->and(UpcomingSong::count())->toBe(0);
    });

    it('keeps the review when the request is later deleted', function () {
        Http::fake(['*' => Http::response(reviewAnswer(4.0))]);
        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 't1']);

        UpcomingSong::sole()->delete();

        expect(AiReview::sole()->upcoming_song_id)->toBeNull();
    });
});

it('tells the AI reviews page whether flagged requests are held', function () {
    $this->party->forceFill(['flag_requests' => false])->save();
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page
            ->where('holdsFlagged', false)
            ->where('settingsUrl', route('parties.edit', $this->party->code).'#flag_requests'));

    $this->party->forceFill(['flag_requests' => true])->save();
    $this->actingAs($this->owner)->get(route('parties.ai-reviews.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('holdsFlagged', true));
});
