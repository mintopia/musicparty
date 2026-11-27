<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingUpdateRequest;
use App\Models\ProviderSetting;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->visibleSettings()
                ->filter(fn (Setting $setting): bool => in_array($setting->type, [SettingType::stString, SettingType::stBoolean], true))
                ->map(fn (Setting $setting): array => [
                    'code' => $setting->code,
                    'name' => $setting->name,
                    'description' => $setting->description,
                    'type' => $setting->type === SettingType::stBoolean ? 'boolean' : 'string',
                    'required' => str_contains($setting->validation ?? '', 'required'),
                    'value' => $setting->type === SettingType::stBoolean ? (bool) $setting->value : (string) ($setting->value ?? ''),
                ])->values(),
            'socialProviders' => SocialProvider::with('settings')->get()->map(fn (SocialProvider $provider): array => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'supports_auth' => (bool) $provider->supports_auth,
                'enabled' => (bool) $provider->enabled,
                'auth_enabled' => (bool) $provider->auth_enabled,
                'needs_secret' => $provider->settings->contains(fn (ProviderSetting $setting): bool => $setting->encrypted && $setting->isRequired() && ($setting->value === null || $setting->value === '')),
                'login_return_url' => route('login.return', $provider->code),
                'linked_account_url' => route('linkedaccounts.store', $provider->code),
            ])->values(),
        ]);
    }

    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        $settings = $this->visibleSettings();
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

    /**
     * @return Collection<int, Setting>
     */
    protected function visibleSettings(): Collection
    {
        return Setting::whereHidden(false)->ordered()->get()
            ->reject(fn (Setting $setting): bool => $setting->code === MusicProviderFactory::FAKE_ENABLED_SETTING && ! MusicProviderFactory::fakeAllowed())
            ->values();
    }
}
