<?php

namespace App\Http\Controllers;

use App\Enums\AiReviewOutcome;
use App\Models\AiReview;
use App\Models\Party;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\Requests\ModerationTabs;
use Illuminate\Http\JsonResponse;
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
            'q' => ['nullable', 'string', 'max:100'],
            'review' => ['nullable', 'integer'],
        ]);
        $focus = isset($filters['review']) ? (int) $filters['review'] : null;
        $outcome = $filters['outcome'] ?? null;
        $search = trim($filters['q'] ?? '');

        $reviews = $party->aiReviews()
            ->with('user:id,nickname')
            ->when($focus !== null, fn ($query) => $query->whereKey($focus))
            ->when($outcome !== null && $outcome !== '', fn ($query) => $query->where('outcome', $outcome))
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
            'filters' => ['outcome' => $outcome ?? '', 'q' => $search, 'review' => $focus],
            'outcomes' => array_map(fn (AiReviewOutcome $case): array => ['code' => $case->value, 'name' => $case->label()], AiReviewOutcome::cases()),
            'retentionDays' => max(1, (int) config('musicparty.ai_review_retention_days')),
            'holdsFlagged' => (bool) $party->flag_requests,
            'settingsUrl' => route('parties.edit', $party->code).'#flag_requests',
            'reviews' => $reviews,
            'moderationTabs' => app(ModerationTabs::class)->for($party),
        ]);
    }

    public function show(Party $party, AiReview $aiReview): JsonResponse
    {
        $this->ensureEnabled($party);
        $aiReview->loadMissing('user:id,nickname');

        return response()->json(['data' => $this->row($aiReview) + [
            'endpointHost' => $aiReview->endpoint_host,
            'score' => $aiReview->score,
            'confidence' => $aiReview->confidence,
            'request' => $aiReview->request,
            'response' => $aiReview->response,
            'partyContext' => $this->partyContext($aiReview),
            'errorClass' => $aiReview->error_class,
            'errorMessage' => $aiReview->error_message,
            'trackId' => $aiReview->track_id,
            'trackProvider' => $aiReview->track_provider,
        ]]);
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
            'driver' => $review->driver,
            'model' => $review->model,
            'latencyMs' => $review->latency_ms,
            'promptTokens' => $review->prompt_tokens,
            'completionTokens' => $review->completion_tokens,
            'totalTokens' => $review->total_tokens,
            'requested' => $review->upcoming_song_id !== null,
        ];
    }

    protected function partyContext(AiReview $review): ?string
    {
        $request = $review->request;
        $payload = $request['state'] ?? ($request['messages'][1]['content'] ?? null);
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        $context = is_array($decoded) ? ($decoded['party_context'] ?? null) : null;

        return is_string($context) ? $context : null;
    }
}
