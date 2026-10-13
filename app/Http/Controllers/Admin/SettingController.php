<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingUpdateRequest;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => Setting::whereHidden(false)->ordered()->get()
                ->filter(fn (Setting $setting): bool => in_array($setting->type, [SettingType::stString, SettingType::stBoolean], true))
                ->map(fn (Setting $setting): array => [
                    'code' => $setting->code,
                    'name' => $setting->name,
                    'description' => $setting->description,
                    'type' => $setting->type === SettingType::stBoolean ? 'boolean' : 'string',
                    'required' => str_contains($setting->validation ?? '', 'required'),
                    'value' => $setting->type === SettingType::stBoolean ? (bool) $setting->value : (string) ($setting->value ?? ''),
                ])->values(),
            'themes' => Theme::get()->map(fn (Theme $theme): array => [
                'id' => $theme->id,
                'name' => $theme->name,
                'active' => (bool) $theme->active,
                'readonly' => (bool) $theme->readonly,
            ])->values(),
            'socialProviders' => SocialProvider::get()->map(fn (SocialProvider $provider): array => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'supports_auth' => (bool) $provider->supports_auth,
                'enabled' => (bool) $provider->enabled,
                'auth_enabled' => (bool) $provider->auth_enabled,
                'login_return_url' => route('login.return', $provider->code),
                'linked_account_url' => route('linkedaccounts.store', $provider->code),
            ])->values(),
        ]);
    }

    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        $settings = Setting::whereHidden(false)->get();
        foreach ($settings as $setting) {
            if ($request->has($setting->code) || $setting->type === SettingType::stBoolean) {
                $value = $request->input($setting->code);
                if ($setting->type === SettingType::stBoolean) {
                    $value = (bool) $value;
                }
                $setting->value = $value;
                $setting->save();
            }
        }

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'Settings have been updated');
    }
}
