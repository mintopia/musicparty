<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\UpdateSiteSettings;
use App\Domain\Admin\SiteSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Http\Resources\V1\AdminSiteSettingsResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(SiteSettings $site): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => new AdminSiteSettingsResource($site)->resolve(),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request, UpdateSiteSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle($request->user() ?? throw new AuthenticationException, $request->validated());

        return back();
    }
}
