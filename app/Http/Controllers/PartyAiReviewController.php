<?php

namespace App\Http\Controllers;

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
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
        $aiReview->loadMissing('user:id,nickname');

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
        abort_unless(app(PartyMods::class)->isEnabled($party, AiRequestReviewMod::CODE), 404);
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
