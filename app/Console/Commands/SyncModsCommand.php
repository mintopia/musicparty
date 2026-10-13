<?php

namespace App\Console\Commands;

use App\Models\Mod;
use App\Models\ModSetting;
use App\Mods\ModRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncModsCommand extends Command
{
    protected $signature = 'mods:sync';

    protected $description = 'Keep the mods and mod_settings tables in line with the mod manifests';

    public function handle(ModRegistry $registry): int
    {
        DB::transaction(function () use ($registry): void {
            foreach ($registry->all() as $manifest) {
                $mod = Mod::whereCode($manifest->code())->first() ?? new Mod;
                $mod->code = $manifest->code();
                $mod->name = $manifest->name();
                $mod->description = $manifest->description();
                $mod->removed_at = null;
                $mod->save();

                foreach ($manifest->settings() as $definition) {
                    $setting = ModSetting::whereMod_id($mod->id)->whereCode($definition->code)->first() ?? new ModSetting;
                    $setting->mod_id = $mod->id;
                    $setting->code = $definition->code;
                    $setting->name = $definition->name;
                    $setting->description = $definition->description;
                    $setting->type = $definition->type;
                    $setting->default = $definition->default;
                    $setting->private = $definition->private;
                    $setting->encrypted = $definition->encrypted;
                    $setting->save();
                }
            }

            $missing = Mod::query()->whereNotIn('code', array_keys($registry->all()))->get();
            foreach ($missing as $mod) {
                DB::table('party_mods')->where('mod_id', $mod->id)->delete();
                if ($mod->removed_at === null) {
                    $mod->removed_at = now();
                    $mod->save();
                }
            }
        });

        $this->components->info('Synced '.count($registry->all()).' mod(s).');

        return self::SUCCESS;
    }
}
