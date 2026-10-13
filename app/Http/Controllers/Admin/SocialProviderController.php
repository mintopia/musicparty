<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialProviderUpdateRequest;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SocialProviderController extends Controller
{
    public function edit(SocialProvider $provider): Response
    {
        return Inertia::render('Admin/SocialProviders/Edit', [
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'can_be_renamed' => (bool) $provider->can_be_renamed,
                'supports_auth' => (bool) $provider->supports_auth,
                'enabled' => (bool) $provider->enabled,
                'auth_enabled' => (bool) $provider->auth_enabled,
            ],
            'settings' => $provider->settings->map(fn (ProviderSetting $setting): array => [
                'code' => $setting->code,
                'name' => $setting->name,
                'description' => $setting->description,
                'required' => $setting->isRequired(),
                'value' => $setting->value === null ? '' : (string) $setting->value,
            ])->values(),
        ]);
    }

    public function update(SocialProviderUpdateRequest $request, SocialProvider $provider): RedirectResponse
    {
        $settings = $provider->settings()->get();
        foreach ($settings as $setting) {
            if ($request->has($setting->code)) {
                $setting->value = $request->input($setting->code);
            } elseif ($setting->type == SettingType::stBoolean) {
                $setting->value = false;
            }
            if ($setting->isDirty()) {
                $setting->save();
            }
        }

        $provider->enabled = (bool) $request->input('enabled');
        if ($provider->supports_auth) {
            $provider->auth_enabled = (bool) $request->input('auth_enabled');
        }
        if ($provider->can_be_renamed) {
            $provider->name = $request->input('name');
        }
        $provider->save();

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', "{$provider->name} has been updated");
    }
}
