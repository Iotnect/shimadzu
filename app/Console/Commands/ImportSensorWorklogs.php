<?php

namespace App\Console\Commands;

use DOMDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

#[Signature('worklogs:import {workbook : Path to the XLSX workbook}')]
#[Description('Import sensor worksheets into private dashboard snapshots')]
class ImportSensorWorklogs extends Command
{
    public function handle(): int
    {
        $path = $this->argument('workbook');
        $directory = config('production.sensor_worklog_directory');
        $archive = new ZipArchive;

        if (! is_string($directory) || $directory === '' || $archive->open($path, ZipArchive::RDONLY) !== true) {
            $this->error('The workbook or snapshot directory is unavailable.');

            return self::FAILURE;
        }

        try {
            $workbook = $this->xml($archive, 'xl/workbook.xml');
            $relationships = [];
            foreach ($this->xml($archive, 'xl/_rels/workbook.xml.rels')->Relationship as $relationship) {
                $relationships[(string) $relationship['Id']] = (string) $relationship['Target'];
            }
            $strings = [];
            if ($archive->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($archive, 'xl/sharedStrings.xml')->si as $item) {
                    $strings[] = $this->cellText($item);
                }
            }

            $generation = (string) Str::uuid();
            $output = $directory.'/'.$generation;
            File::ensureDirectoryExists($output);
            $importedAt = now()->toIso8601String();
            $sensors = [];
            $imported = 0;
            $published = false;

            foreach ($workbook->sheets->sheet as $sheet) {
                $worksheet = (string) $sheet['name'];
                if (! preg_match('/^([a-z0-9]+)_worklog$/iD', $worksheet, $matches)) {
                    continue;
                }

                $sensor = strtolower($matches[1]);
                $relationship = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $relationships[$relationship] ?? '';
                if (! preg_match('~^(?:/xl/)?(worksheets/[^/]+\.xml)$~D', $target, $parts)) {
                    throw new RuntimeException("Worksheet {$worksheet} is missing.");
                }

                $jobs = $this->readJobs($archive, 'xl/'.$parts[1], $strings);
                if ($jobs === []) {
                    throw new RuntimeException("Worksheet {$worksheet} has no worklog records.");
                }

                $snapshot = json_encode([
                    'source' => basename($path),
                    'worksheet' => $worksheet,
                    'importedAt' => $importedAt,
                    'jobs' => $jobs,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if (File::put($output.'/'.$sensor.'.json', $snapshot) !== strlen($snapshot)) {
                    throw new RuntimeException("Could not save worksheet {$worksheet}.");
                }

                $sensors[] = $sensor;
                $imported += count($jobs);
                $this->line($worksheet.': '.count($jobs).' records');
            }

            if ($sensors === []) {
                $this->error('No sensor worklog worksheets were found.');

                return self::FAILURE;
            }

            File::replace($directory.'/index.json', json_encode(['generation' => $generation, 'sensors' => $sensors], JSON_THROW_ON_ERROR));
            $published = true;
        } finally {
            $archive->close();
            if (isset($output) && ! $published) {
                File::deleteDirectory($output);
            }
        }

        $this->info(number_format($imported).' records imported.');

        return self::SUCCESS;
    }

    private function xml(ZipArchive $archive, string $part): SimpleXMLElement
    {
        $contents = $archive->getFromName($part);
        $xml = $contents === false ? false : simplexml_load_string($contents, options: LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException("The workbook part {$part} is unreadable.");
        }

        return $xml;
    }

    private function cellText(SimpleXMLElement $item): string
    {
        return implode('', array_map('strval', $item->xpath('./*[local-name()="t"] | ./*[local-name()="r"]/*[local-name()="t"]')));
    }

    /**
     * @param  list<string>  $strings
     * @return list<array<string, mixed>>
     */
    private function readJobs(ZipArchive $archive, string $part, array $strings): array
    {
        $contents = $archive->getFromName($part);
        if ($contents === false) {
            throw new RuntimeException("The workbook part {$part} is missing.");
        }

        $reader = new XMLReader;
        if (! $reader->XML($contents, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("The workbook part {$part} is unreadable.");
        }

        $jobs = [];
        try {
            while ($reader->read() && ! ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row')) {
                //
            }

            while ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                $row = simplexml_import_dom($reader->expand(new DOMDocument));
                $cells = [];
                foreach ($row->c as $cell) {
                    $column = rtrim((string) $cell['r'], '0123456789');
                    if (strlen($column) !== 1 || $column > 'M' || isset($cell->f) || (string) $cell['t'] === 'e') {
                        continue;
                    }
                    $raw = (string) $cell->v;
                    $value = match ((string) $cell['t']) {
                        's' => $strings[(int) $raw] ?? '',
                        'inlineStr' => $this->cellText($cell->is),
                        default => $raw,
                    };
                    $value = trim($value);
                    $cells[$column] = $value === '' || strtoupper($value) === 'NULL' ? null : $value;
                }

                if ((int) $row['r'] === 1) {
                    $headers = ['id', 'StartTime', 'StopTime', 'Place', 'UnitName', 'Worker', 'SN', 'UnitTime', 'WorkTime', 'instmanual', 'skill', 'qty', 'jobcode'];
                    if (array_values($cells) !== $headers) {
                        throw new RuntimeException("The workbook part {$part} has unexpected headers.");
                    }
                } elseif (isset($cells['A'])) {
                    $jobs[] = $this->job($cells, (int) $row['r']);
                }

                $reader->next('row');
            }
        } finally {
            $reader->close();
        }

        return $jobs;
    }

    /**
     * @param  array<string, string|null>  $cells
     * @return array<string, mixed>
     */
    private function job(array $cells, int $row): array
    {
        if (! is_numeric($cells['A']) || ! is_numeric($cells['B'] ?? null) || ! is_numeric($cells['C'] ?? null) || ! preg_match('/^St[1-9][0-9]*$/D', $cells['D'] ?? '')) {
            throw new RuntimeException("Row {$row} has an invalid ID, timestamp or station.");
        }

        $serial = $cells['G'] ?? null;
        $unitTime = $cells['H'] ?? null;
        $workTime = $cells['I'] ?? null;
        $reference = $cells['J'] ?? null;
        $skill = $cells['K'] ?? null;
        $quantity = $cells['L'] ?? null;
        $jobCode = $cells['M'] ?? null;

        // Some exported rows omit a field entirely and shift qty and jobcode one or two columns left.
        if ($jobCode === null && $reference === null && $skill === '1' && $quantity === '0') {
            $jobCode = $quantity;
            $quantity = $skill;
            $skill = null;

            if (is_numeric($serial) && (float) $serial > 0 && (float) $serial < 1
                && abs(((float) $cells['C'] - (float) $cells['B'] - (float) $serial) * 86400) < 1) {
                [$serial, $unitTime, $workTime, $reference] = [null, $serial, $unitTime, $workTime];
            }
        } elseif ($jobCode === null && $quantity === null && $reference === '1' && $skill === '0') {
            [$reference, $skill, $quantity, $jobCode] = [null, null, $reference, $skill];
        }

        return [
            'id' => (int) $cells['A'],
            'start' => gmdate('Y-m-d\TH:i:s', (int) round(((float) $cells['B'] - 25569) * 86400)),
            'end' => gmdate('Y-m-d\TH:i:s', (int) round(((float) $cells['C'] - 25569) * 86400)),
            'station' => $cells['D'],
            'product' => $cells['E'] ?? null,
            'worker' => $cells['F'] ?? null,
            'serial' => $serial,
            'unitMinutes' => $this->minutes($unitTime),
            'workMinutes' => $this->minutes($workTime),
            'reference' => $reference,
            'skill' => $skill,
            'quantity' => is_numeric($quantity) ? (float) $quantity : null,
            'jobCode' => $jobCode,
            'sourceRow' => $row,
        ];
    }

    private function minutes(?string $value): ?float
    {
        if (is_numeric($value)) {
            return round((float) $value * 1440, 4);
        }

        if (preg_match('/^(-?)(\d+):([0-5]\d):([0-5]\d)$/D', $value ?? '', $parts)) {
            return round(($parts[1] === '-' ? -1 : 1) * ($parts[2] * 60 + $parts[3] + $parts[4] / 60), 4);
        }

        return null;
    }
}
