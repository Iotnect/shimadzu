<?php

namespace App\Services;

use App\Models\StationSetting;
use Illuminate\Support\Facades\Schema;

class StationSettings
{
    private const DET_STANDARD_MINUTES = ['St1' => 25.515, 'St2' => 37.24, 'St3' => 23.695, 'St4' => 14.8925];

    private ?bool $available = null;

    public function available(): bool
    {
        return $this->available ??= config('auth.providers.users.driver') === 'eloquent' && Schema::hasTable('station_settings');
    }

    /** @return array<string, list<array{effective_from: string, standard: ?float, working: float}>> */
    public function versions(string $sensor, string $through): array
    {
        if (! $this->available()) {
            return [];
        }

        $versions = [];
        foreach (StationSetting::query()->where('sensor', $sensor)->whereDate('effective_from', '<=', $through)
            ->orderBy('effective_from')->orderBy('id')->get() as $setting) {
            $versions[$setting->station][] = [
                'effective_from' => $setting->effective_from->toDateString(),
                'standard' => $setting->standard_minutes === null ? null : (float) $setting->standard_minutes,
                'working' => (float) $setting->working_minutes,
            ];
        }

        return $versions;
    }

    /**
     * @param  list<array{effective_from: string, standard: ?float, working: float}>  $versions
     * @return array{standard: ?float, working: float, effective_from: ?string}
     */
    public function resolve(string $sensor, string $station, string $date, array $versions): array
    {
        $parameters = [
            'standard' => $sensor === ProductionDashboard::DEFAULT_SENSOR ? (self::DET_STANDARD_MINUTES[$station] ?? null) : null,
            'working' => 435.0,
            'effective_from' => null,
        ];

        foreach ($versions as $version) {
            if ($version['effective_from'] > $date) {
                break;
            }
            $parameters = $version;
        }

        return $parameters;
    }
}
