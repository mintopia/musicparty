<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\UpdateSiteSettings;
use App\Domain\Admin\SiteSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSiteSettingsRequest;
use App\Http\Resources\V1\AdminSiteSettingsResource;
use Illuminate\Auth\AuthenticationException;

class SettingsController extends Controller
{
    public function show(SiteSettings $site): AdminSiteSettingsResource
    {
        return new AdminSiteSettingsResource($site);
    }

    public function update(UpdateSiteSettingsRequest $request, UpdateSiteSettings $updateSettings): AdminSiteSettingsResource
    {
        return new AdminSiteSettingsResource($updateSettings->handle($request->user() ?? throw new AuthenticationException, $request->validated()));
    }
}
