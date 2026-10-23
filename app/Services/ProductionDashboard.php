<?php

namespace App\Services;

class ProductionDashboard
{
    private const STANDARD_TIMES = ['St1' => 25.515, 'St2' => 37.24, 'St3' => 23.695, 'St4' => 14.8925];

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return json_decode(file_get_contents(storage_path('app/private/det-worklog.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function overview(?string $date = null, string $station = 'all'): array
    {
        $snapshot = $this->snapshot();
        $dates = array_values(array_unique(array_map(fn (array $job): string => substr($job['start'], 0, 10), $snapshot['jobs'])));
        sort($dates);
        $stations = array_values(array_unique(array_column($snapshot['jobs'], 'station')));
        sort($stations);
        $date ??= end($dates);
        $jobs = array_values(array_filter($snapshot['jobs'], fn (array $job): bool => str_starts_with($job['start'], $date) && ($station === 'all' || $job['station'] === $station)));
        usort($jobs, fn (array $left, array $right): int => [$left['start'], $left['id']] <=> [$right['start'], $right['id']]);
        $selectedStations = $station === 'all' ? $stations : [$station];
        $previousDates = array_values(array_filter($dates, fn (string $day): bool => $day < $date));
        $previousDate = $previousDates === [] ? null : end($previousDates);
        $previousJobs = array_values(array_filter($snapshot['jobs'], fn (array $job): bool => $previousDate !== null && str_starts_with($job['start'], $previousDate) && ($station === 'all' || $job['station'] === $station)));

        return [
            'date' => $date, 'station' => $station, 'dates' => $dates, 'stations' => $stations,
            'summary' => $this->summarize($jobs, $selectedStations),
            'previous' => $previousDate === null ? null : ['date' => $previousDate, ...$this->summarize($previousJobs, $selectedStations)],
            'jobs' => $jobs,
            'source' => [
                'name' => $snapshot['source'], 'importedAt' => $snapshot['importedAt'],
                'records' => count($snapshot['jobs']), 'from' => $dates[0], 'to' => end($dates),
                'standardSource' => 'DET cycle timeline.pbix',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @param  array<int, string>  $stations
     * @return array<string, mixed>
     */
    public function summarize(array $jobs, array $stations): array
    {
        $rows = [];
        $segments = [];

        foreach ($stations as $station) {
            $stationJobs = array_values(array_filter($jobs, fn (array $job): bool => $job['station'] === $station));
            usort($stationJobs, fn (array $left, array $right): int => [$left['start'], $left['id']] <=> [$right['start'], $right['id']]);
            $running = 0;
            $idle = 0;
            $lastEnd = null;

            foreach ($stationJobs as $job) {
                $start = strtotime($job['start']);
                $end = strtotime($job['end']);
                if ($end < $start) {
                    continue;
                }
                $duration = ($end - $start) / 60;
                if ($lastEnd !== null && $start > $lastEnd && ($start - $lastEnd) < 43200 && date('Y-m-d', $lastEnd) === substr($job['start'], 0, 10)) {
                    $gap = ($start - $lastEnd) / 60;
                    $idle += $gap;
                    $segments[] = ['station' => $station, 'status' => 'Idle', 'start' => date('Y-m-d\TH:i:s', $lastEnd), 'end' => $job['start'], 'minutes' => round($gap, 2)];
                }
                $running += max(0, $end - max($start, $lastEnd ?? $start)) / 60;
                $segments[] = ['station' => $station, 'status' => 'Running', 'start' => $job['start'], 'end' => $job['end'], 'minutes' => round($duration, 2), 'jobId' => $job['id']];
                $lastEnd = max($lastEnd ?? $end, $end);
            }

            $standard = self::STANDARD_TIMES[$station] ?? null;
            $expected = $standard === null ? null : count($stationJobs) * $standard;
            $rows[] = [
                'station' => $station, 'cycles' => count($stationJobs),
                'quantity' => array_sum(array_column($stationJobs, 'quantity')),
                'missingQuantity' => count(array_filter($stationJobs, fn (array $job): bool => $job['quantity'] === null)),
                'running' => round($running, 2), 'idle' => round($idle, 2), 'available' => 435,
                'efficiency' => $stationJobs === [] ? null : round($running / 435 * 100, 1),
                'availability' => $running + $idle > 0 ? round($running / ($running + $idle) * 100, 1) : null,
                'performance' => $running > 0 && $expected !== null ? round($expected / $running * 100, 1) : null,
                'standard' => $standard, 'expected' => $expected,
            ];
        }

        $running = array_sum(array_column($rows, 'running'));
        $idle = array_sum(array_column($rows, 'idle'));
        $available = count($stations) * 435;
        $expected = array_sum(array_column($rows, 'expected'));
        $hasMissingStandard = count(array_filter($rows, fn (array $row): bool => $row['cycles'] > 0 && $row['standard'] === null)) > 0;

        return [
            'rows' => $rows, 'segments' => $segments, 'cycles' => count($jobs),
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
