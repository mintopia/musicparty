<?php

namespace App\Http\Controllers;

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use App\Exceptions\PartyFeatureDisabledException;
use App\Models\AiReview;
use App\Models\Party;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\AiRequestReview\AiReviewBreaker;
use App\Mods\AiRequestReview\DecisionModelReviewer;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Requests\ModerationTabs;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyAiReviewController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(Request $request, Party $party): Response
    {
        $this->ensureEnabled($party);
        $filters = $request->validate([
            'outcome' => ['nullable', 'string', 'in:'.implode(',', array_column(AiReviewOutcome::cases(), 'value'))],
            'criterion' => ['nullable', 'string', 'in:'.implode(',', array_column(AiReviewCriterion::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $outcome = $filters['outcome'] ?? null;
        $criterion = $filters['criterion'] ?? null;
        $search = trim($filters['q'] ?? '');

        $reviews = $party->aiReviews()
            ->with('user:id,nickname')
            ->when($outcome !== null && $outcome !== '', fn ($query) => $query->where('outcome', $outcome))
            ->when($criterion !== null && $criterion !== '', fn ($query) => $query->where('criterion', $criterion))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner
                    ->where('track_title', 'like', $like)
                    ->orWhere('track_artists', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('nickname', 'like', $like)));
            })
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AiReview $review): array => $this->row($review));

        return Inertia::render('Parties/AiReviews/Index', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'playerState' => $party->getState(),
            'filters' => ['outcome' => $outcome ?? '', 'criterion' => $criterion ?? '', 'q' => $search],
            'criteria' => array_map(fn (AiReviewCriterion $case): array => ['code' => $case->value, 'name' => $case->label()], AiReviewCriterion::cases()),
            'outcomes' => array_map(fn (AiReviewOutcome $case): array => ['code' => $case->value, 'name' => $case->label()], AiReviewOutcome::cases()),
            'holdsFlagged' => (bool) $party->flag_requests,
            'settingsUrl' => route('parties.edit', $party->code).'#flag_requests',
            'counts' => $this->counts($party),
            'breaker' => app(AiReviewBreaker::class)->state($party),
            'reviews' => $reviews,
            'moderationTabs' => app(ModerationTabs::class)->for($party),
        ]);
    }

    public function show(Request $request, Party $party, AiReview $aiReview): Response
    {
        $this->ensureEnabled($party);
        $filters = $request->validate([
            'outcome' => ['nullable', 'string', 'in:'.implode(',', array_column(AiReviewOutcome::cases(), 'value'))],
            'criterion' => ['nullable', 'string', 'in:'.implode(',', array_column(AiReviewCriterion::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $aiReview->loadMissing(['user:id,nickname', 'upcomingSong.played']);
        $percentage = app(ModSettingsStore::class)->value($party, AiRequestReviewMod::CODE, 'confidence_percentage');

        return Inertia::render('Parties/AiReviews/Show', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'playerState' => $party->getState(),
            'moderationTabs' => app(ModerationTabs::class)->for($party),
            'criterionLabels' => collect(AiReviewCriterion::cases())->mapWithKeys(fn (AiReviewCriterion $case): array => [$case->value => $case->label()])->all(),
            'backUrl' => route('parties.ai-reviews.index', [$party->code] + array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== '')),
            'review' => $this->row($aiReview) + [
                'endpointHost' => $aiReview->endpoint_host,
                'score' => $aiReview->score,
                'confidence' => $aiReview->confidence,
                'request' => $aiReview->request,
                'response' => $aiReview->response,
                'errorClass' => $aiReview->error_class,
                'errorMessage' => $aiReview->error_message,
                'trackId' => $aiReview->track_id,
                'trackProvider' => $aiReview->track_provider,
                'threshold' => (is_numeric($percentage) ? (float) $percentage : DecisionModelReviewer::DEFAULT_CONFIDENCE_PERCENTAGE) / 100,
                'rejectBelow' => DecisionModelReviewer::REJECT_BELOW,
                'flagBelow' => DecisionModelReviewer::FLAG_BELOW,
                'fate' => $this->fate($party, $aiReview),
            ],
        ]);
    }

    /**
     * @return array{total: int, outcome: array<string, int>, criterion: array<string, int>}
     */
    protected function counts(Party $party): array
    {
        $byOutcome = $party->aiReviews()->toBase()->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome');
        $byCriterion = $party->aiReviews()->toBase()->whereNotNull('criterion')->selectRaw('criterion, count(*) as total')->groupBy('criterion')->pluck('total', 'criterion');

        return [
            'total' => (int) $byOutcome->sum(),
            'outcome' => $byOutcome->map(fn (mixed $total): int => (int) $total)->all(),
            'criterion' => $byCriterion->map(fn (mixed $total): int => (int) $total)->all(),
        ];
    }

    protected function ensureEnabled(Party $party): void
    {
        if (! app(PartyMods::class)->isEnabled($party, AiRequestReviewMod::CODE)) {
            throw PartyFeatureDisabledException::aiReviews();
        }
    }

    /**
     * @return array{state: string, requestUrl: string|null, approveUrl: string|null, rejectUrl: string|null, flaggedAt: array{iso: string, human: string}|null, playedAt: array{iso: string, human: string}|null}
     */
    protected function fate(Party $party, AiReview $review): array
    {
        $upcoming = $review->upcomingSong;
        if ($upcoming === null) {
            return ['state' => $review->outcome === AiReviewOutcome::Reject ? 'rejected' : 'gone', 'requestUrl' => $review->outcome === AiReviewOutcome::Reject ? route('parties.songs.index', $party->code) : null, 'approveUrl' => null, 'rejectUrl' => null, 'flaggedAt' => null, 'playedAt' => null];
        }

        $held = $upcoming->flagged_at !== null;
        $playedAt = $upcoming->played?->played_at;

        return [
            'state' => $held ? 'held' : ($playedAt !== null ? 'played' : 'queued'),
            'requestUrl' => route('parties.songs.show', [$party->code, $upcoming->id]),
            'approveUrl' => $held ? route('parties.flagged.approve', [$party->code, $upcoming->id]) : null,
            'rejectUrl' => $held ? route('parties.flagged.reject', [$party->code, $upcoming->id]) : null,
            'flaggedAt' => $upcoming->flagged_at === null ? null : ['iso' => $upcoming->flagged_at->toIso8601String(), 'human' => $upcoming->flagged_at->diffForHumans()],
            'playedAt' => $playedAt === null ? null : ['iso' => $playedAt->toIso8601String(), 'human' => $playedAt->diffForHumans()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(AiReview $review): array
    {
        return [
            'id' => $review->id,
            'createdAt' => $review->created_at === null ? null : [
                'iso' => $review->created_at->toIso8601String(),
                'human' => $review->created_at->diffForHumans(),
            ],
            'user' => $review->user?->nickname,
            'track' => ['title' => $review->track_title, 'artists' => $review->track_artists],
            'outcome' => $review->outcome->value,
            'outcomeLabel' => $review->outcome->label(),
            'reason' => $review->reason,
            'criterion' => $review->criterion?->value,
            'criterionLabel' => $review->criterion?->label(),
            'criterionConfidence' => $review->criterion_confidence,
            'driver' => $review->driver,
            'model' => $review->model,
            'latencyMs' => $review->latency_ms,
            'promptTokens' => $review->prompt_tokens,
            'completionTokens' => $review->completion_tokens,
            'totalTokens' => $review->total_tokens,
            'requested' => $review->upcoming_song_id !== null,
        ];
    }
}
