<?php

namespace App\Console\Commands;

use App\Domain\Admin\Actions\SaveProviderSetting;
use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Identity\Actions\SeedSocialProviders;
use App\Domain\Identity\Actions\SetSocialProviderEnabled;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ConfigureProviders extends Command
{
    protected $signature = 'providers:configure {provider? : Provider code}';

    protected $description = 'Interactively configure a login provider: credentials, enabled and login flags';

    public function handle(SaveProviderSetting $save, SetSocialProviderEnabled $setEnabled): int
    {
        $code = $this->argument('provider') ?? select('Provider', array_keys(SeedSocialProviders::PROVIDERS));

        $class = SeedSocialProviders::PROVIDERS[$code] ?? null;
        if ($class === null) {
            $this->error("Unknown provider: {$code}");

            return self::FAILURE;
        }

        $provider = (new $class)->install();

        foreach ($provider->settings()->ordered()->get() as $setting) {
            /** @var ProviderSetting $setting */
            $label = $setting->name.($setting->isRequired() ? ' (required)' : '');
            $current = (string) $setting->value;

            if ($setting->encrypted) {
                $input = password($label.($current !== '' ? ' [leave blank to keep]' : ''));
                $value = $input === '' ? $current : $input;
            } else {
                $value = text($label, default: $current);
            }

            $setting->value = $value === '' ? null : $value;
            $save($setting);
        }

        $enabled = confirm('Enable provider?', (bool) $provider->enabled);
        $authEnabled = $enabled && $provider->supports_auth && confirm('Allow login with it?', (bool) $provider->auth_enabled);

        try {
            $setEnabled($provider, $enabled, $authEnabled);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("{$code}: ".($provider->enabled ? 'enabled' : 'disabled').', login '.($provider->auth_enabled ? 'on' : 'off'));

        return self::SUCCESS;
    }
}
