<?php

namespace Database\Factories;

use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyModSetting>
 */
class PartyModSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'mod_setting_id' => ModSetting::factory(),
            'value' => fake()->word(),
        ];
    }
}
