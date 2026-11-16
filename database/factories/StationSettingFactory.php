<?php

namespace Database\Factories;

use App\Models\StationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StationSetting>
 */
class StationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sensor' => 'lc2kdet',
            'station' => 'St1',
            'effective_from' => '2026-01-01',
            'standard_minutes' => 30,
            'working_minutes' => 450,
            'notes' => fake()->sentence(),
            'changed_by_name' => 'Test administrator',
        ];
    }
}
