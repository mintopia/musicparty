<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModSetting>
 */
class ModSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mod_id' => Mod::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->slug(1),
            'type' => SettingType::stString,
            'order' => 0,
        ];
    }
}
