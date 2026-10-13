<?php

use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Models\Role;
use App\Models\User;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FooModFixture;

beforeEach(function () {
    $this->withoutVite();
    FooModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $this->mod = Mod::whereCode('foo')->firstOrFail();
    $this->mod->settings()->delete();
    $order = 0;
    $make = function (string $code, SettingType $type, array $extra = []) use (&$order) {
        return ModSetting::factory()->create(array_merge([
            'mod_id' => $this->mod->id, 'code' => $code, 'type' => $type, 'order' => $order++,
        ], $extra));
    };
    $make('title', SettingType::stString, ['default' => 'Hello', 'validation' => 'max:10']);
    $make('flag', SettingType::stBoolean, ['default' => false]);
    $make('count', SettingType::stInteger, ['default' => 3, 'validation' => 'min:1|max:10']);
    $make('ratio', SettingType::stFloat, ['default' => 1.5]);
    $make('opens', SettingType::stTimeOfDay, ['default' => '10:00']);
    $make('song', SettingType::stTrack, ['default' => 'defaulttrack1']);
    $make('hidden', SettingType::stString, ['private' => true, 'default' => 'x']);
    $make('required_text', SettingType::stString, ['validation' => 'required']);
    $make('api_key', SettingType::stSecret, ['instance_only' => true, 'encrypted' => true]);
    $make('instance_name', SettingType::stString, ['instance_only' => true, 'default' => 'inst']);
    $make('party_secret', SettingType::stSecret, ['encrypted' => true]);

    $this->owner = User::factory()->withSpotify()->create();
    $this->owner->forceFill(['first_login' => false])->save();
    $this->party = Party::factory()->for($this->owner)->create();
    $this->store = new ModSettingsStore;
    $this->def = fn (string $code) => ModSetting::whereCode($code)->firstOrFail();
});

function putMods(Party $party, array $settings, ?bool $enabled = null): array
{
    $entry = ['settings' => $settings];
    if ($enabled !== null) {
        $entry['enabled'] = $enabled;
    }

    return ['mods' => ['foo' => $entry]];
}

describe('store validation', function () {
    it('stores valid values for each type and returns defaults when unset', function () {
        expect($this->store->value($this->party, 'foo', 'count'))->toBe(3)
            ->and($this->store->value($this->party, 'foo', 'opens'))->toBe('10:00');

        $this->store->set($this->party, 'foo', 'title', 'Hi');
        $this->store->set($this->party, 'foo', 'flag', '1');
        $this->store->set($this->party, 'foo', 'count', '7');
        $this->store->set($this->party, 'foo', 'ratio', '2.25');
        $this->store->set($this->party, 'foo', 'opens', '23:30');

        $fresh = new ModSettingsStore;
        expect($fresh->value($this->party, 'foo', 'title'))->toBe('Hi')
            ->and($fresh->value($this->party, 'foo', 'flag'))->toBeTrue()
            ->and($fresh->value($this->party, 'foo', 'count'))->toBe(7)
            ->and($fresh->value($this->party, 'foo', 'ratio'))->toBe(2.25)
            ->and($fresh->value($this->party, 'foo', 'opens'))->toBe('23:30');
    });

    it('rejects invalid values keyed by the setting code', function (string $code, mixed $value) {
        try {
            $this->store->set($this->party, 'foo', $code, $value);
            $this->fail('expected a validation failure');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($code);
        }
        expect(PartyModSetting::count())->toBe(0);
    })->with([
        'string too long' => ['title', 'way too long for this'],
        'boolean' => ['flag', 'maybe'],
        'integer' => ['count', 'abc'],
        'integer over max' => ['count', 99],
        'float' => ['ratio', 'x'],
        'time of day' => ['opens', '25:99'],
        'time of day format' => ['opens', '9am'],
        'track format' => ['song', 'not a track!'],
        'track unknown scheme' => ['song', 'other:track:abc'],
        'required missing' => ['required_text', null],
    ]);

    it('stores the provider_track_id for a track, resolving a provider reference', function () {
        app(MusicProviderFactory::class)->register('fake', fn (User $user, Purpose $purpose, ?string $country = null) => new FakeMusicProvider);
        $party = Party::factory()->for($this->owner)->create(['provider_id' => 'fake']);

        $this->store->set($party, 'foo', 'song', 'fake:track:abc-123');
        expect((new ModSettingsStore)->value($party, 'foo', 'song'))->toBe('abc-123');

        $this->store->set($party, 'foo', 'song', 'plainid42');
        expect((new ModSettingsStore)->value($party, 'foo', 'song'))->toBe('plainid42');
    });

    it('refuses to store an instance-only setting per party', function () {
        $this->store->set($this->party, 'foo', 'instance_name', 'x');
    })->throws(InvalidArgumentException::class);
});

describe('secrets', function () {
    it('stores a secret encrypted and keeps it when set empty', function () {
        $this->store->set($this->party, 'foo', 'party_secret', 'hunter2');

        $raw = DB::table('party_mod_settings')->value('value');
        expect($raw)->not->toContain('hunter2')
            ->and((new ModSettingsStore)->value($this->party, 'foo', 'party_secret'))->toBe('hunter2');

        $this->store->set($this->party, 'foo', 'party_secret', '');
        $this->store->set($this->party, 'foo', 'party_secret', null);
        expect((new ModSettingsStore)->value($this->party, 'foo', 'party_secret'))->toBe('hunter2');
    });

    it('never presents the secret value', function () {
        $this->store->set($this->party, 'foo', 'party_secret', 'hunter2');
        $this->store->setInstance('foo', 'api_key', 'topsecret');

        $json = json_encode([$this->store->presentFor($this->party), $this->store->presentInstance()]);
        expect($json)->not->toContain('hunter2')->not->toContain('topsecret');
        $secret = collect($this->store->presentFor($this->party)[0]['settings'])->firstWhere('code', 'party_secret');
        expect($secret['value'])->toBeNull()->and($secret['has_value'])->toBeTrue()->and($secret['type'])->toBe('secret');
    });
});

describe('instance-only settings', function () {
    it('lives in mod_settings.instance_value, encrypted for secrets, and is read through value()', function () {
        expect($this->store->value($this->party, 'foo', 'instance_name'))->toBe('inst');

        $this->store->setInstance('foo', 'instance_name', 'renamed');
        $this->store->setInstance('foo', 'api_key', 'topsecret');

        expect($this->store->instanceValue('foo', 'instance_name'))->toBe('renamed')
            ->and($this->store->value($this->party, 'foo', 'instance_name'))->toBe('renamed')
            ->and(PartyModSetting::count())->toBe(0)
            ->and(DB::table('mod_settings')->where('code', 'api_key')->value('instance_value'))->not->toContain('topsecret')
            ->and($this->store->value($this->party, 'foo', 'api_key'))->toBe('topsecret');

        $this->store->setInstance('foo', 'api_key', '');
        expect($this->store->instanceValue('foo', 'api_key'))->toBe('topsecret');
    });

    it('validates instance writes', function () {
        $this->store->setInstance('foo', 'instance_name', ['array']);
    })->throws(ValidationException::class);
});

describe('party Mods screen', function () {
    it('exposes only Host-editable settings in the contract shape', function () {
        $this->store->set($this->party, 'foo', 'count', 5);

        $this->actingAs($this->owner)->get(route('parties.mods.edit', $this->party->code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Parties/Mods')
                ->where('party.code', $this->party->code)
                ->where('mods.0.code', 'foo')
                ->where('mods.0.enabled', false)
                ->where('mods.0.settings.2', fn ($setting) => $setting['code'] === 'count' && $setting['value'] === 5 && $setting['default'] === 3 && $setting['type'] === 'integer' && $setting['has_value'] === true && $setting['required'] === false)
                ->where('mods.0.settings', fn ($settings) => collect($settings)->pluck('code')->all() === ['title', 'flag', 'count', 'ratio', 'opens', 'song', 'required_text', 'party_secret'])
            );
    });

    it('saves settings and enablement', function () {
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), putMods($this->party, [
            'title' => 'Yo', 'flag' => true, 'count' => 4, 'opens' => '08:15', 'required_text' => 'ok', 'party_secret' => 's3cret',
        ], true))->assertSessionHasNoErrors()->assertRedirect(route('parties.mods.edit', $this->party->code));

        $store = new ModSettingsStore;
        expect($store->value($this->party, 'foo', 'title'))->toBe('Yo')
            ->and($store->value($this->party, 'foo', 'count'))->toBe(4)
            ->and($store->value($this->party, 'foo', 'party_secret'))->toBe('s3cret')
            ->and(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeTrue();
    });

    it('returns errors keyed by mod and setting and writes nothing', function () {
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), putMods($this->party, [
            'title' => 'fine', 'count' => 'abc', 'opens' => 'bad',
        ], true))->assertSessionHasErrors(['mods.foo.settings.count', 'mods.foo.settings.opens']);

        expect(PartyModSetting::count())->toBe(0)->and(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeFalse();
    });

    it('rejects private and instance-only settings', function () {
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), putMods($this->party, ['hidden' => 'x']))
            ->assertSessionHasErrors('mods.foo.settings.hidden');
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), putMods($this->party, ['instance_name' => 'x', 'api_key' => 'y']))
            ->assertSessionHasErrors(['mods.foo.settings.instance_name', 'mods.foo.settings.api_key']);
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), ['mods' => ['nope' => ['enabled' => true]]])
            ->assertSessionHasErrors('mods.nope');

        expect(PartyModSetting::count())->toBe(0);
    });

    it('never puts a secret in the response', function () {
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), putMods($this->party, ['party_secret' => 'hunter2']));

        $this->actingAs($this->owner)->get(route('parties.mods.edit', $this->party->code))
            ->assertOk()->assertDontSee('hunter2')
            ->assertInertia(fn (Assert $page) => $page->where('mods.0.settings.7', fn ($setting) => $setting['value'] === null && $setting['has_value'] === true));
    });

    it('is host-only', function () {
        $this->get(route('parties.mods.edit', $this->party->code))->assertRedirect();
        $this->put(route('parties.mods.update', $this->party->code), ['mods' => []])->assertRedirect();

        $other = User::factory()->withSpotify()->create();
        $other->forceFill(['first_login' => false])->save();
        $this->actingAs($other)->get(route('parties.mods.edit', $this->party->code))->assertForbidden();
        $this->actingAs($other)->put(route('parties.mods.update', $this->party->code), putMods($this->party, ['title' => 'x']))->assertForbidden();
    });
});

describe('admin Mods screen', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['first_login' => false])->save();
        $this->admin->roles()->attach(Role::query()->firstWhere('code', 'admin') ?? Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    });

    it('lists only instance-only settings, without secret values', function () {
        $this->store->setInstance('foo', 'api_key', 'topsecret');
        Mod::factory()->create(['code' => 'plain', 'name' => 'Plain']);

        $this->actingAs($this->admin)->get(route('admin.mods.edit'))
            ->assertOk()->assertDontSee('topsecret')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Mods')
                ->has('mods', 1)
                ->where('mods.0.code', 'foo')
                ->where('mods.0.settings', fn ($settings) => collect($settings)->pluck('code')->all() === ['api_key', 'instance_name'])
                ->where('mods.0.settings.0.has_value', true)
                ->where('mods.0.settings.0.value', null)
            );
    });

    it('updates instance settings and rejects party-level ones', function () {
        $this->actingAs($this->admin)->put(route('admin.mods.update'), ['mods' => ['foo' => ['settings' => ['instance_name' => 'new', 'api_key' => 'k3y']]]])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.mods.edit'));
        expect($this->store->instanceValue('foo', 'instance_name'))->toBe('new')
            ->and($this->store->instanceValue('foo', 'api_key'))->toBe('k3y');

        $this->actingAs($this->admin)->put(route('admin.mods.update'), ['mods' => ['foo' => ['settings' => ['title' => 'x', 'hidden' => 'y']]]])
            ->assertSessionHasErrors(['mods.foo.settings.title', 'mods.foo.settings.hidden']);
    });

    it('is admin-only', function () {
        $this->get(route('admin.mods.edit'))->assertRedirect();
        $this->actingAs($this->owner)->get(route('admin.mods.edit'))->assertForbidden();
        $this->actingAs($this->owner)->put(route('admin.mods.update'), ['mods' => []])->assertForbidden();
    });
});
