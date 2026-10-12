<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\PairingToggles;
use App\Domain\Identity\Models\User;
use App\Enums\SettingType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetMusicProviderEnabled
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, string $id, bool $enabled): void
    {
        if (! array_key_exists($id, (array) config('musicparty.music_providers', []))) {
            throw ValidationException::withMessages(['music_provider' => 'Unknown music provider.']);
        }

        DB::transaction(function () use ($admin, $id, $enabled): void {
            $label = (string) config("musicparty.music_providers.{$id}.label", $id);
            $setting = Setting::query()->where('code', PairingToggles::providerCode($id))->first() ?? new Setting;
            $setting->forceFill([
                'code' => PairingToggles::providerCode($id),
                'name' => "{$label} enabled",
                'encrypted' => false,
                'hidden' => true,
                'validation' => '',
                'type' => SettingType::stBoolean,
                'value' => $enabled,
            ])->save();

            $this->audit->handle($admin, $enabled ? 'music_provider.enabled' : 'music_provider.disabled', null, ['music_provider' => $id]);
        });
    }
}
