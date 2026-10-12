<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\PairingToggles;
use App\Domain\Identity\Models\User;
use App\Enums\SettingType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPlayerEnabled
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, string $kind, bool $enabled): void
    {
        if (! array_key_exists($kind, (array) config('musicparty.players', []))) {
            throw ValidationException::withMessages(['player' => 'Unknown player kind.']);
        }

        DB::transaction(function () use ($admin, $kind, $enabled): void {
            $label = (string) config("musicparty.players.{$kind}.label", $kind);
            $setting = Setting::query()->where('code', PairingToggles::playerCode($kind))->first() ?? new Setting;
            $setting->forceFill([
                'code' => PairingToggles::playerCode($kind),
                'name' => "{$label} enabled",
                'encrypted' => false,
                'hidden' => true,
                'validation' => '',
                'type' => SettingType::stBoolean,
                'value' => $enabled,
            ])->save();

            $this->audit->handle($admin, $enabled ? 'player.enabled' : 'player.disabled', null, ['player' => $kind]);
        });
    }
}
