<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\SocialProviders\AbstractSocialProvider;
use App\Domain\Identity\SocialProviders\DiscordProvider;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Identity\SocialProviders\SteamProvider;
use App\Domain\Identity\SocialProviders\TwitchProvider;
use Illuminate\Support\Facades\DB;

class SeedSocialProviders
{
    /**
     * @var array<string, class-string<AbstractSocialProvider>>
     */
    public const PROVIDERS = [
        'discord' => DiscordProvider::class,
        'twitch' => TwitchProvider::class,
        'steam' => SteamProvider::class,
        'spotify' => SpotifyProvider::class,
    ];

    /**
     * @return array<string, bool> provider code => enabled
     */
    public function __invoke(): array
    {
        $result = [];

        foreach (self::PROVIDERS as $code => $class) {
            $result[$code] = DB::transaction(fn (): bool => $this->seed($code, new $class));
        }

        return $result;
    }

    private function seed(string $code, AbstractSocialProvider $installer): bool
    {
        $existed = SocialProvider::query()->where('code', $code)->exists();
        $provider = $installer->install();

        $allConfigured = true;

        foreach ($provider->settings()->get() as $setting) {
            /** @var ProviderSetting $setting */
            $configured = config("services.{$code}.{$setting->code}");

            if (blank($setting->value) && filled($configured)) {
                $setting->value = $configured;
                $setting->save();
            }

            if ($setting->isRequired() && blank($setting->value)) {
                $allConfigured = false;
            }
        }

        if (! $existed && $allConfigured) {
            $provider->enabled = true;
            $provider->auth_enabled = true;
            $provider->save();
        }

        return $provider->enabled && $provider->auth_enabled;
    }
}
