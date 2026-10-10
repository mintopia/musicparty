<?php

namespace App\Domain\Admin;

use App\Domain\Identity\SocialProviders\DiscordProvider;
use App\Domain\Identity\SocialProviders\FacebookProvider;
use App\Domain\Identity\SocialProviders\GoogleProvider;
use App\Domain\Identity\SocialProviders\LaravelPassportProvider;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Identity\SocialProviders\SteamProvider;
use App\Domain\Identity\SocialProviders\TwitchProvider;

final class ProviderCatalogue
{
    public const string MASK = '********';

    /**
     * @return array<string, array{name: string, class: class-string, fields: array<string, array{name: string, secret: bool}>}>
     */
    public static function all(): array
    {
        $oauthFields = [
            'client_id' => ['name' => 'Client ID', 'secret' => false],
            'client_secret' => ['name' => 'Client Secret', 'secret' => true],
        ];

        return [
            'discord' => ['name' => 'Discord', 'class' => DiscordProvider::class, 'fields' => $oauthFields],
            'twitch' => ['name' => 'Twitch', 'class' => TwitchProvider::class, 'fields' => $oauthFields],
            'steam' => ['name' => 'Steam', 'class' => SteamProvider::class, 'fields' => [
                'client_secret' => ['name' => 'API Key', 'secret' => true],
            ]],
            'laravelpassport' => ['name' => 'Laravel Passport', 'class' => LaravelPassportProvider::class, 'fields' => $oauthFields + [
                'host' => ['name' => 'Passport Host', 'secret' => false],
            ]],
            'spotify' => ['name' => 'Spotify', 'class' => SpotifyProvider::class, 'fields' => $oauthFields],
            'google' => ['name' => 'Google', 'class' => GoogleProvider::class, 'fields' => $oauthFields],
            'facebook' => ['name' => 'Facebook', 'class' => FacebookProvider::class, 'fields' => $oauthFields],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }
}
