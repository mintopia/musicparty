<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\ListPairingAvailability;
use App\Domain\Admin\Actions\ListSocialProviders;
use App\Domain\Admin\Actions\UpdateSiteSettings;
use App\Domain\Admin\SiteSettings;
use App\Domain\Theming\Actions\GetInstanceTheme;
use App\Domain\Theming\ThemeTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Http\Resources\V1\AdminSiteSettingsResource;
use App\Http\Resources\V1\AdminSocialProviderResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(SiteSettings $site, ListSocialProviders $listProviders, GetInstanceTheme $getInstanceTheme, ListPairingAvailability $listPairing): Response
    {
        $theme = $getInstanceTheme->handle();

        return Inertia::render('Admin/Settings', [
            'settings' => new AdminSiteSettingsResource($site)->resolve(),
            'providers' => AdminSocialProviderResource::collection($listProviders->handle())->resolve(),
            ...$listPairing->handle(),
            'theme' => ['light' => $theme['light'], 'dark' => $theme['dark'], 'font' => ThemeTokens::FONTS[$theme['font']]['label'] ?? $theme['font']],
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request, UpdateSiteSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle($request->user() ?? throw new AuthenticationException, $request->validated());

        return back()->with('success', 'Settings saved.');
    }
}
