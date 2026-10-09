<?php

namespace App\Domain\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SiteSettings
{
    public const FIELDS = [
        'name' => ['code' => 'name', 'label' => 'Site Name'],
        'terms_url' => ['code' => 'terms', 'label' => 'Terms and Conditions URL'],
        'privacy_url' => ['code' => 'privacypolicy', 'label' => 'Privacy Policy URL'],
        'default_party' => ['code' => 'defaultparty', 'label' => 'Default Party Code'],
        'logo_light' => ['code' => 'logo-light', 'label' => 'Site Logo (Light Version)'],
        'logo_dark' => ['code' => 'logo-dark', 'label' => 'Site Logo (Dark Version)'],
        'favicon' => ['code' => 'favicon', 'label' => 'Favicon'],
    ];

    public const FILE_FIELDS = ['logo_light', 'logo_dark', 'favicon'];

    public static function disk(): string
    {
        return (string) config('musicparty.site_disk', 'public');
    }

    public function get(string $field): ?string
    {
        $value = Setting::fetch(self::FIELDS[$field]['code']);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function name(): string
    {
        return $this->get('name') ?? (string) config('app.name');
    }

    public function fileUrl(string $field): ?string
    {
        $path = $this->get($field);

        return $path === null ? null : Storage::disk(self::disk())->url($path);
    }

    /**
     * @return array{name: string, logo_light_url: ?string, logo_dark_url: ?string, favicon_url: ?string, terms_url: ?string, privacy_url: ?string, default_party: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'logo_light_url' => $this->fileUrl('logo_light'),
            'logo_dark_url' => $this->fileUrl('logo_dark'),
            'favicon_url' => $this->fileUrl('favicon'),
            'terms_url' => $this->get('terms_url'),
            'privacy_url' => $this->get('privacy_url'),
            'default_party' => $this->get('default_party'),
        ];
    }
}
