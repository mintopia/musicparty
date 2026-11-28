<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiReviewOutcome;
use App\Http\Controllers\Controller;
use App\Models\AiReview;
use App\Models\Mod;
use App\Services\ModSettingsStore;
use App\Services\ModSettingsUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ModsController extends Controller
{
    public function edit(ModSettingsStore $store): Response
    {
        $withSettings = collect($store->presentInstance())->keyBy('code');
        $mods = Mod::query()->whereNull('removed_at')->orderBy('name')->get()->map(fn (Mod $mod): array => [
            'code' => $mod->code,
            'name' => $mod->name,
            'description' => $mod->description,
            'settings' => $withSettings->get($mod->code)['settings'] ?? [],
            'review_stats' => $mod->code === 'ai-request-review' ? $this->reviewStats() : null,
        ])->values()->all();

        return Inertia::render('Admin/Mods', ['mods' => $mods]);
    }

    /**
     * @return array{last_review_at: string|null, reviews_today: int, last_failure: array{at: string, message: string|null}|null}
     */
    protected function reviewStats(): array
    {
        $last = AiReview::query()->max('created_at');
        $failure = AiReview::query()->where('outcome', AiReviewOutcome::Error)->latest('id')->first(['created_at', 'error_message']);

        return [
            'last_review_at' => $last === null ? null : Carbon::parse($last, 'UTC')->toIso8601String(),
            'reviews_today' => AiReview::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'last_failure' => $failure?->created_at === null ? null : ['at' => $failure->created_at->toIso8601String(), 'message' => $failure->error_message],
        ];
    }

    public function update(Request $request, ModSettingsUpdater $updater): RedirectResponse
    {
        $validated = $request->validate(['mods' => ['present', 'array']]);

        $updater->updateInstance($validated['mods']);

        return redirect()->route('admin.mods.edit');
    }

    public function updateMod(Request $request, string $mod, ModSettingsUpdater $updater): RedirectResponse
    {
        $validated = $request->validate(['settings' => ['present', 'array']]);

        $updater->updateInstance([$mod => $validated]);

        return redirect()->route('admin.mods.edit');
    }
}
