<?php

namespace App\Http\Controllers\Admin;

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
     * @return array{last_review_at: string|null, reviews_today: int}
     */
    protected function reviewStats(): array
    {
        $last = AiReview::query()->max('created_at');

        return [
            'last_review_at' => $last === null ? null : Carbon::parse($last, 'UTC')->toIso8601String(),
            'reviews_today' => AiReview::query()->where('created_at', '>=', now()->startOfDay())->count(),
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
