<?php

use App\Domain\Admin\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('reads a changed setting fresh once the shared cache entry is cleared', function (): void {
    $setting = Setting::query()->forceCreate(['code' => 'site.name', 'name' => 'Site name', 'value' => 'First', 'order' => 1]);

    expect(Setting::fetch('site.name'))->toBe('First');

    Cache::forget('settings.site.name');
    Setting::query()->where('code', 'site.name')->update(['value' => 'Second']);

    expect(Setting::fetch('site.name'))->toBe('Second');
});

it('reflects an update made through the model in the next fetch', function (): void {
    $setting = Setting::query()->forceCreate(['code' => 'site.name', 'name' => 'Site name', 'value' => 'First', 'order' => 1]);
    Setting::fetch('site.name');

    $setting->forceFill(['value' => 'Second'])->save();

    expect(Setting::fetch('site.name'))->toBe('Second');
});

it('returns the default for a missing setting', function (): void {
    expect(Setting::fetch('missing.code', 'fallback'))->toBe('fallback');
});
