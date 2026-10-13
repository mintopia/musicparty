<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModSettingsStore;
use App\Services\ModSettingsUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModsController extends Controller
{
    public function edit(ModSettingsStore $store): Response
    {
        return Inertia::render('Admin/Mods', [
            'mods' => $store->presentInstance(),
        ]);
    }

    public function update(Request $request, ModSettingsUpdater $updater): RedirectResponse
    {
        $validated = $request->validate(['mods' => ['present', 'array']]);

        $updater->updateInstance($validated['mods']);

        return redirect()->route('admin.mods.edit');
    }
}
