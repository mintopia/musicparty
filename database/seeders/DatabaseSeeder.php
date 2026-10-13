<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(PartyMemberRoleSeeder::class);
        $this->call(SocialProvidersSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(ThemesSeeder::class);

        Artisan::call('mods:sync');
    }
}
