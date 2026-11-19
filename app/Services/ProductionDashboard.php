<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;

class ProductionDashboard
{
    public const DEFAULT_SENSOR = 'lc2kdet';

    /** @var array{generation: string, sensors: list<string>}|null */
    private ?array $sensorCatalog = null;

    public function __construct(private StationSettings $settings) {}

    /** @return list<string> */
    public function sensors(): array
    {
        return $this->catalog()['sensors'];
    }

    /** @return array{generation: string, sensors: list<string>} */
    private function catalog(): array
    {
        if ($this->sensorCatalog !== null) {
            return $this->sensorCatalog;
        }

        $directory = config('production.sensor_worklog_directory');
        $path = $directory.'/index.json';
        if (! is_string($directory) || $directory === '' || ! is_file($path)) {
            return $this->sensorCatalog = ['generation' => '', 'sensors' => [self::DEFAULT_SENSOR]];
        }

        $catalog = json_decode(file_get_contents($path), true);
        abort_unless(
            is_string($catalog['generation'] ?? null)
            && preg_match('/^[a-f0-9-]{36}$/D', $catalog['generation'])
            && is_array($catalog['sensors'] ?? null)
            && array_is_list($catalog['sensors'])
            && $catalog['sensors'] !== []
            && collect($catalog['sensors'])->every(fn (mixed $sensor): bool => is_string($sensor) && preg_match('/^[a-z0-9]+$/D', $sensor)),
            503,
            'Production data is temporarily unavailable.',
        );

        return $this->sensorCatalog = $catalog;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $url = config('production.worklog_url');

        if (is_string($url) && $url !== '') {
            abort_unless(filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https', 503, 'Production data is temporarily unavailable.');

            $token = config('production.worklog_token');

            return Cache::remember('production-snapshot:'.hash('sha256', $url.'|'.$token), 300, function () use ($url, $token): array {
                $request = Http::acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting();

                if (is_string($token) && $token !== '') {
                    $request = $request->withToken($token);
                }

                try {
                    $response = $request->get($url)->throw();
                    abort_unless($response->successful(), 503, 'Production data is temporarily unavailable.');

                    return $this->decodeSnapshot($response->body());
                } catch (ConnectionException|RequestException $exception) {
                    abort(503, 'Production data is temporarily unavailable.');
                }
            });
        }

        $path = config('production.worklog_path');

        if ((! is_string($path) || ! is_file($path)) && config('production.demo_mode')) {
            $path = config('production.demo_worklog_path');
        }

        abort_unless(is_string($path) && is_file($path) && is_readable($path), 503, 'Production data is temporarily unavailable.');

        return $this->decodeSnapshot(file_get_contents($path));
    }

    /** @return array<string, mixed> */
    private function decodeSnapshot(string $contents): array
    {
        try {
            $snapshot = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            abort(503, 'Production data is temporarily unavailable.');
        }

        abort_unless(
            is_array($snapshot)
            && is_string($snapshot['source'] ?? null)
            && is_string($snapshot['importedAt'] ?? null)
            && is_array($snapshot['jobs'] ?? null)
            && array_is_list($snapshot['jobs'])
            && $snapshot['jobs'] !== [],
            503,
            'Production data is temporarily unavailable.',
        );

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function sensorSnapshot(string $sensor): array
    {
        $catalog = $this->catalog();
        abort_unless(in_array($sensor, $catalog['sensors'], true), 404);

        if ($catalog['generation'] === '') {
            return $this->snapshot();
        }

        $path = config('production.sensor_worklog_directory').'/'.$catalog['generation'].'/'.$sensor.'.json';
        abort_unless(is_readable($path), 503, 'Production data is temporarily unavailable.');

        return $this->decodeSnapshot(file_get_contents($path));
    }

    /** @return list<string> */
    public function stationNames(string $sensor): array
    {
        $stations = array_values(array_unique(array_column($this->sensorSnapshot($sensor)['jobs'], 'station')));
        natsort($stations);

        return array_values($stations);
    }

    /** @return array<string, mixed> */
    public function overview(
        ?string $date = null,
        string $station = 'all',
        ?string $sensor = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $catalog = $this->catalog();
        $sensor ??= in_array(self::DEFAULT_SENSOR, $catalog['sensors'], true) ? self::DEFAULT_SENSOR : $catalog['sensors'][0];
        $snapshot = $this->sensorSnapshot($sensor);

        $dates = array_values(array_unique(array_map(fn (array $job): string => substr($job['start'], 0, 10), $snapshot['jobs'])));
        sort($dates);
        $stations = array_values(array_unique(array_column($snapshot['jobs'], 'station')));
        natsort($stations);
        $stations = array_values($stations);
        $latest = end($dates);
        $from ??= $date ?? $latest;
        $to ??= $date ?? $latest;
        $periodDates = array_values(array_filter($dates, fn (string $day): bool => $day >= $from && $day <= $to));
        $date = $date !== null && $date >= $from && $date <= $to ? $date : (end($periodDates) ?: $to);
        $jobs = array_values(array_filter($snapshot['jobs'], function (array $job) use ($from, $to, $station): bool {
            $day = substr($job['start'], 0, 10);

            return $day >= $from && $day <= $to && ($station === 'all' || $job['station'] === $station);
        }));
        usort($jobs, fn (array $left, array $right): int => [$left['start'], $left['id']] <=> [$right['start'], $right['id']]);
        $selectedStations = $station === 'all' ? $stations : [$station];
        $previousDates = $from === $to ? array_values(array_filter($dates, fn (string $day): bool => $day < $date)) : [];
        $previousDate = $previousDates === [] ? null : end($previousDates);
        $previousJobs = array_values(array_filter($snapshot['jobs'], fn (array $job): bool => $previousDate !== null && str_starts_with($job['start'], $previousDate) && ($station === 'all' || $job['station'] === $station)));
        $summary = $this->summarize($jobs, $selectedStations, $from === $to ? 1 : count($periodDates), $sensor, $date, $from === $to ? [$from] : $periodDates);

        return [
            'sensor' => $sensor, 'sensors' => $catalog['sensors'],
            'sensorLabels' => config('production.sensor_labels', []),
            'date' => $date, 'from' => $from, 'to' => $to, 'periodDates' => $periodDates,
            'station' => $station, 'dates' => $dates, 'stations' => $stations,
            'summary' => $summary,
            'previous' => $previousDate === null ? null : ['date' => $previousDate, ...$this->summarize($previousJobs, $selectedStations, sensor: $sensor, workDates: [$previousDate])],
            'jobs' => $jobs,
            'source' => [
                'isDemo' => (bool) ($snapshot['demo'] ?? false),
                'name' => $snapshot['source'], 'importedAt' => $snapshot['importedAt'],
                'records' => count($snapshot['jobs']), 'from' => $dates[0], 'to' => $latest,
                'worksheet' => $snapshot['worksheet'] ?? $sensor.'_worklog',
                'standardSource' => $summary['usesCustomSettings'] ? 'Station Settings' : ($sensor === self::DEFAULT_SENSOR ? 'Original cycle timeline report' : null),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  array<int, string>  $stations
     * @param  list<string>|null  $workDates
     * @return array<string, mixed>
     */
    public function summarize(array $jobs, array $stations, int $workdays = 1, string $sensor = self::DEFAULT_SENSOR, ?string $timelineDate = null, ?array $workDates = null): array
    {
        $rows = [];
        $segments = [];
        $runningCount = 0;
        $idleCount = 0;
        $workDates ??= array_values(array_unique(array_map(fn (array $job): string => substr($job['start'], 0, 10), $jobs)));
        if ($workDates === [] && $workdays > 0) {
            $workDates = [$timelineDate ?? now('Asia/Kuala_Lumpur')->toDateString()];
        }
        sort($workDates);
        $versions = $this->settings->versions($sensor, $workDates === [] ? '0001-01-01' : end($workDates));
        $usesCustomSettings = false;
        $jobsByStation = [];
        foreach ($jobs as $job) {
            $jobsByStation[$job['station']][] = $job;
        }

        foreach ($stations as $station) {
            $parameters = [];
            foreach ($workDates as $day) {
                $parameters[$day] = $this->settings->resolve($sensor, $station, $day, $versions[$station] ?? []);
                $usesCustomSettings = $usesCustomSettings || $parameters[$day]['effective_from'] !== null;
            }
            $dailyMinutes = array_sum(array_column($parameters, 'working'));
            $standards = array_values(array_unique(array_column($parameters, 'standard'), SORT_REGULAR));
            $standard = count($standards) === 1 ? $standards[0] : null;
            $stationJobs = $jobsByStation[$station] ?? [];
            usort($stationJobs, fn (array $left, array $right): int => [$left['start'], $left['id']] <=> [$right['start'], $right['id']]);
            $running = 0;
            $idle = 0;
            $stationRunningCount = 0;
            $stationIdleCount = 0;
            $lastEnd = null;

            foreach ($stationJobs as $job) {
                $start = strtotime($job['start'].' UTC');
                $end = strtotime($job['end'].' UTC');
                if ($end < $start) {
                    continue;
                }
                $duration = ($end - $start) / 60;
                $day = substr($job['start'], 0, 10);
                $onTimeline = $timelineDate === null || $day === $timelineDate;
                if ($lastEnd !== null && $start > $lastEnd && ($start - $lastEnd) < 43200 && gmdate('Y-m-d', $lastEnd) === $day) {
                    $gap = ($start - $lastEnd) / 60;
                    $idle += $gap;
                    $idleCount++;
                    $stationIdleCount++;
                    if ($onTimeline) {
                        $segments[] = ['station' => $station, 'status' => 'Idle', 'start' => gmdate('Y-m-d\TH:i:s', $lastEnd), 'end' => $job['start'], 'minutes' => round($gap, 2)];
                    }
                }
                $running += max(0, $end - max($start, $lastEnd ?? $start)) / 60;
                $runningCount++;
                $stationRunningCount++;
                if ($onTimeline) {
                    $segments[] = ['station' => $station, 'status' => 'Running', 'start' => $job['start'], 'end' => $job['end'], 'minutes' => round($duration, 2), 'jobId' => $job['id']];
                }
                $lastEnd = max($lastEnd ?? $end, $end);
            }

            $expected = 0;
            $missingStandard = false;
            foreach ($stationJobs as $job) {
                $day = substr($job['start'], 0, 10);
                $jobStandard = $parameters[$day]['standard'] ?? null;
                $missingStandard = $missingStandard || $jobStandard === null;
                $expected += $jobStandard ?? 0;
            }
            $expected = $missingStandard || ($stationJobs === [] && $standard === null) ? null : round($expected, 4);
            $rows[] = [
                'station' => $station, 'cycles' => count($stationJobs),
                'runningCount' => $stationRunningCount, 'idleCount' => $stationIdleCount,
                'quantity' => array_sum(array_column($stationJobs, 'quantity')),
                'missingQuantity' => count(array_filter($stationJobs, fn (array $job): bool => $job['quantity'] === null)),
                'running' => round($running, 2), 'idle' => round($idle, 2), 'available' => $dailyMinutes,
                'efficiency' => $stationJobs === [] || $dailyMinutes === 0 ? null : round($running / $dailyMinutes * 100, 1),
                'availability' => $running + $idle > 0 ? round($running / ($running + $idle) * 100, 1) : null,
                'performance' => $running > 0 && $expected !== null ? round($expected / $running * 100, 1) : null,
                'standard' => $standard, 'expected' => $expected,
                'standardVaries' => count($standards) > 1,
            ];
        }

        $running = array_sum(array_column($rows, 'running'));
        $idle = array_sum(array_column($rows, 'idle'));
        $available = array_sum(array_column($rows, 'available'));
        $expected = array_sum(array_column($rows, 'expected'));
        $hasMissingStandard = count(array_filter($rows, fn (array $row): bool => $row['expected'] === null && ($row['cycles'] > 0 || $jobs === []))) > 0;

        return [
            'rows' => $rows, 'segments' => $segments, 'cycles' => count($jobs),
            'runningCount' => $runningCount, 'idleCount' => $idleCount,
            'usesCustomSettings' => $usesCustomSettings,
            'quantity' => array_sum(array_column($rows, 'quantity')),
            'missingQuantity' => array_sum(array_column($rows, 'missingQuantity')),
            'running' => round($running, 1), 'idle' => round($idle, 1), 'available' => $available,
            'expected' => $hasMissingStandard ? null : round($expected, 1),
            'efficiency' => $jobs !== [] && $available > 0 ? round($running / $available * 100, 1) : null,
            'availability' => $running + $idle > 0 ? round($running / ($running + $idle) * 100, 1) : null,
            'performance' => $running > 0 && ! $hasMissingStandard ? round($expected / $running * 100, 1) : null,
        ];
    }
}
