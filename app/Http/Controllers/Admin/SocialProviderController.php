<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SocialProviderUpdateRequest;
use App\Models\SocialProvider;

class SocialProviderController extends Controller
{
    public function edit(SocialProvider $provider)
    {
        return view('admin.socialproviders.edit', [
            'provider' => $provider,
        ]);
    }

    public function update(SocialProviderUpdateRequest $request, SocialProvider $provider)
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

        $provider->enabled = (bool)$request->input('enabled');
        if ($provider->supports_auth) {
            $provider->auth_enabled = (bool)$request->input('auth_enabled');
        }
        if ($provider->can_be_renamed) {
            $provider->name = $request->input('name');
        }
        $provider->save();
        return response()->redirectToRoute('admin.settings.index')->with('successMessage', "{$provider->name} has been updated");
    }
}
