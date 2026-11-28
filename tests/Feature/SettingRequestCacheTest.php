<?php

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function makeSetting(string $code, mixed $value, bool $encrypted = false): Setting
{
    $setting = (new Setting)->forceFill(['code' => $code, 'name' => $code, 'encrypted' => $encrypted, 'order' => 1, 'type' => SettingType::stString]);
    $setting->value = $value;
    $setting->save();

    return $setting;
}

beforeEach(function () {
    Cache::flush();
    Setting::flushRequestCache();
});

it('reads a setting changed by another request fresh on the next request', function () {
    makeSetting('name', 'Old');
    expect(Setting::fetch('name'))->toBe('Old');

    DB::table('settings')->where('code', 'name')->update(['value' => serialize('New')]);
    Cache::forget('settings.name');
    Setting::flushRequestCache();

    expect(Setting::fetch('name'))->toBe('New');
});

it('returns plaintext every time an encrypted setting is read', function () {
    makeSetting('secretish', 'hunter2', true);
    Setting::flushRequestCache();

    expect(Setting::fetch('secretish'))->toBe('hunter2')
        ->and(Setting::fetch('secretish'))->toBe('hunter2');

    Setting::flushRequestCache();
    expect(Setting::fetch('secretish'))->toBe('hunter2')
        ->and(Setting::fetch('secretish'))->toBe('hunter2');
});

it('queries the database once for a missing key within the cache TTL', function () {
    DB::enableQueryLog();

    expect(Setting::fetch('nope', 'fallback'))->toBe('fallback');
    Setting::flushRequestCache();
    expect(Setting::fetch('nope', 'other'))->toBe('other');

    expect(collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'settings')))->toHaveCount(1);
});
