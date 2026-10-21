<?php
namespace App\Mods\Whamageddon\Commands;

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    public $signature = 'mods:whamageddon:install';

    public function handle(): int
    {
        $this->installMod();
        return self::SUCCESS;
    }

    protected function installMod(): void
    {
        $mod = Mod::whereCode('whamageddon')->first();
        if (!$mod) {
            $mod = new Mod();
            $mod->code = 'whamageddon';
        }
        $mod->name = 'Whamageddon';
        $mod->description = "Last Christmas will be added to the queue. Every hour between 10AM and 1AM, 1 upvote will be added. Will the party be able to avoid it?";
        $mod->save();
        $this->addSetting(
            mod: $mod,
            code: 'enabled',
            name: 'Enable',
            type: SettingType::stBoolean,
            default: false
        );
        $this->addSetting(
            mod: $mod,
            code: 'spotify_id',
            name: 'Spotify Track ID',
            type: SettingType::stString,
            default: '2FRnf9qhLbvw8fu4IBXx78',
        );
        $this->addSetting(
            mod: $mod,
            code: 'votes',
            name: 'Votes per Hour',
            type: SettingType::stInteger,
            default: 1,
        );
        $this->addSetting(
            mod: $mod,
            code: 'fallback_name',
            name: 'Fallback Name Override',
            type: SettingType::stString,
            default: 'Whamageddon',
        );
        $this->addSetting(
            mod: $mod,
            code: 'css_classes',
            name: 'CSS Classes',
            type: SettingType::stString,
            default: 'whamageddon',
        );
        $this->addSetting(
            mod: $mod,
            code: 'upcoming_song_id',
            name: 'Upcoming Song ID',
            type: SettingType::stInteger,
            private: true,
        );
    }

    protected function addSetting(
        Mod $mod,
        string $code,
        string $name,
        SettingType $type,
        ?string $description = null,
        mixed $default = null,
        bool $private = false,
        bool $encrypted = false,
    ): ModSetting
    {
        $setting = $mod->settings()->whereCode($code)->first();
        if (!$setting) {
            $setting = new ModSetting();
            $setting->code = $code;
            $setting->mod()->associate($mod);
        }
        $setting->name = $name;
        $setting->description = $description;
        $setting->type = $type;
        $setting->default = $default;
        $setting->private = $private;
        $setting->encrypted = $encrypted;
        $setting->save();

        return $setting;
    }

}
