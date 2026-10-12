<?php

namespace App\Domain\Admin;

use App\Domain\Admin\Models\Setting;

class PairingToggles
{
    public function providerEnabled(string $id): bool
    {
        return (bool) Setting::fetch(self::providerCode($id), false);
    }

    public function playerEnabled(string $kind): bool
    {
        return (bool) Setting::fetch(self::playerCode($kind), false);
    }

    public static function providerCode(string $id): string
    {
        return "music_provider.{$id}.enabled";
    }

    public static function playerCode(string $kind): string
    {
        return "player.{$kind}.enabled";
    }
}
