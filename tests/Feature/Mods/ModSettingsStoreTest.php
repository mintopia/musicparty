<?php

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Services\ModSettingsStore;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->mod = Mod::factory()->create(['code' => 'demo']);
    foreach (['alpha', 'beta', 'gamma'] as $order => $code) {
        ModSetting::factory()->create([
            'mod_id' => $this->mod->id,
            'code' => $code,
            'type' => SettingType::stString,
            'order' => $order,
            'default' => 'default-'.$code,
        ]);
    }
    $this->party = Party::factory()->create();
    $this->store = new ModSettingsStore;
});

test('setting a value with no stored row creates it', function () {
    expect(PartyModSetting::count())->toBe(0);

    $this->store->set($this->party, 'demo', 'alpha', 'hello');

    expect(PartyModSetting::count())->toBe(1)
        ->and((new ModSettingsStore)->value($this->party, 'demo', 'alpha'))->toBe('hello');
});

test('unset values fall back to the definition default', function () {
    expect($this->store->value($this->party, 'demo', 'beta'))->toBe('default-beta')
        ->and($this->store->value($this->party, 'demo', 'missing', 'fallback'))->toBe('fallback');
});

test('setting an unknown definition throws', function () {
    $this->store->set($this->party, 'demo', 'missing', 'x');
})->throws(InvalidArgumentException::class);

test('loading a party settings issues one query', function () {
    DB::enableQueryLog();

    $this->store->value($this->party, 'demo', 'alpha');
    $this->store->value($this->party, 'demo', 'beta');
    $this->store->value($this->party, $this->mod, 'gamma');

    expect(DB::getQueryLog())->toHaveCount(1);
});

test('delete reverts to the default and is not stale', function () {
    $this->store->set($this->party, 'demo', 'alpha', 'hello');
    $this->store->delete($this->party, 'demo', 'alpha');

    expect(PartyModSetting::count())->toBe(0)
        ->and($this->store->value($this->party, 'demo', 'alpha'))->toBe('default-alpha');
});

test('settings are isolated per party', function () {
    $other = Party::factory()->create();
    $this->store->set($this->party, 'demo', 'alpha', 'mine');

    expect($this->store->value($other, 'demo', 'alpha'))->toBe('default-alpha');
});
