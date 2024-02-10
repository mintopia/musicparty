<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Services\TicketProviders\InternalTicketProvider;
use App\Services\TicketProviders\TicketTailorProvider;
use App\Services\TicketProviders\WooCommerceProvider;
use Illuminate\Database\Seeder;

class ThemesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $themes = [
            [
                'name' => 'Default',
                'code' => 'default',
                'readonly' => true,
                'primary' => '#0054a6',
                'nav_background' => '#182433',
                'css' => '',
            ],
        ];
        foreach ($themes as $data) {
            $theme = Theme::whereCode($data['code'])->first();
            if (!$theme) {
                $theme = new Theme;
                $theme->code = $data['code'];
            }
            foreach ($data as $prop => $value) {
                $theme->{$prop} = $value;
            }
            $theme->save();
        }
    }
}
