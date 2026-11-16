<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SensorWorklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['production.sensor_worklog_directory' => Storage::disk('local')->path('sensors')]);
        $this->actingAs(User::factory()->make());
    }

    public function test_overview_exposes_the_sensor_catalog_and_sensor_pages_have_their_own_sections(): void
    {
        $this->snapshots();

        $this->get('/?station=St1')->assertOk()->assertSee('id="sensor-options"', false)
            ->assertDontSee(route('sensors.show', 'aoc'))
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['station'] === 'all' && $dashboard['sensors'] === ['aoc', 'lc2kdet']);
        $response = $this->get('/sensors/aoc')->assertOk()->assertViewHas('sensorPage', 'aoc')->assertSee('AOC worklog');
        $this->assertSame('aoc', $response['dashboard']['sensor']);
        $this->assertSame(['St1', 'St7'], $response['dashboard']['stations']);
    }

    public function test_sensor_and_inclusive_range_filter_all_totals_and_scale_capacity_by_recorded_days(): void
    {
        $this->snapshots();

        $this->getJson('/dashboard/data?sensor=aoc&from=2026-05-25&to=2026-05-27')
            ->assertOk()->assertJsonPath('summary.cycles', 3)
            ->assertJsonPath('summary.running', 180)->assertJsonPath('summary.idle', 15)
            ->assertJsonPath('summary.available', 1740)->assertJsonPath('summary.quantity', 3)
            ->assertJsonPath('summary.performance', null)->assertJsonPath('previous', null)
            ->assertJsonPath('summary.runningCount', 3)->assertJsonPath('summary.idleCount', 1)
            ->assertJsonPath('date', '2026-05-27')->assertJsonCount(2, 'periodDates')
            ->assertJsonPath('source.records', 4)->assertJsonCount(3, 'jobs');
    }

    public function test_section_selection_stays_within_the_sensor_in_the_route(): void
    {
        $this->snapshots();

        $this->getJson('/sensors/aoc/data?sensor=lc2kdet&from=2026-05-25&to=2026-05-27&station=St7')
            ->assertOk()->assertJsonPath('sensor', 'aoc')->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.id', 3)->assertJsonPath('summary.available', 870)
            ->assertJsonPath('summary.running', 60)->assertJsonPath('summary.missingQuantity', 1);
    }

    public function test_changing_timeline_day_preserves_the_range_totals(): void
    {
        $this->snapshots();

        $this->getJson('/sensors/aoc/data?from=2026-05-25&to=2026-05-27&date=2026-05-25')
            ->assertOk()->assertJsonPath('summary.cycles', 3)
            ->assertJsonPath('summary.running', 180)->assertJsonPath('date', '2026-05-25')
            ->assertJsonCount(3, 'summary.segments')
            ->assertJsonPath('summary.segments.0.start', '2026-05-25T09:00:00');
    }

    public function test_empty_periods_and_invalid_filters_are_handled(): void
    {
        $this->snapshots();

        $this->getJson('/sensors/aoc/data?from=2026-05-01&to=2026-05-10')
            ->assertOk()->assertJsonCount(0, 'jobs')->assertJsonPath('summary.efficiency', null)
            ->assertJsonPath('summary.performance', null)->assertJsonPath('summary.available', 0);
        $this->getJson('/dashboard/data?from=2026-05-27&to=2026-05-25')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/dashboard/data?from=2026-05-25')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/dashboard/data?sensor=unknown')
            ->assertUnprocessable()->assertJsonValidationErrors('sensor');
        $this->get('/sensors/unknown')->assertNotFound();
        $this->getJson('/sensors/unknown/data')->assertNotFound();
    }

    public function test_sensor_endpoints_require_authentication(): void
    {
        $this->snapshots();
        Auth::forgetGuards();

        $this->get('/sensors/aoc')->assertRedirect(route('login'));
        $this->getJson('/sensors/aoc/data')->assertUnauthorized();
    }

    public function test_import_reads_each_sheet_preserves_zero_and_handles_shifted_fields_and_serial_formulas(): void
    {
        $workbook = $this->workbook();

        $this->artisan('worklogs:import', ['workbook' => $workbook])->assertSuccessful();
        $this->getJson('/sensors/aoc/data?date=2024-10-01')->assertOk()
            ->assertJsonCount(3, 'jobs')->assertJsonPath('jobs.0.quantity', 0)
            ->assertJsonPath('jobs.1.quantity', 1)->assertJsonPath('jobs.1.serial', null)
            ->assertJsonPath('jobs.1.reference', 'instruction.pdf')
            ->assertJsonPath('jobs.1.unitMinutes', 15)
            ->assertJsonPath('jobs.1.workMinutes', 7.5)
            ->assertJsonPath('jobs.2.serial', null)->assertJsonPath('source.worksheet', 'aoc_worklog')
            ->assertJsonPath('summary.performance', null);
        $this->getJson('/sensors/lc2kdet/data?date=2024-10-01')->assertOk()
            ->assertJsonCount(1, 'jobs')->assertJsonPath('summary.quantity', 0)
            ->assertJsonPath('jobs.0.serial', '000123')->assertJsonPath('jobs.0.sourceRow', 2);
    }

    public function test_failed_import_leaves_the_previous_sensor_generation_readable(): void
    {
        $this->snapshots();
        $workbook = $this->workbook(invalidSecondSheet: true);

        try {
            $this->artisan('worklogs:import', ['workbook' => $workbook])->run();
            $this->fail('The malformed second sheet should reject the import.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('invalid ID, timestamp or station', $exception->getMessage());
        }

        $this->getJson('/sensors/aoc/data?date=2026-05-25')->assertOk()->assertJsonPath('source.name', 'fixture.xlsx')
            ->assertJsonPath('summary.cycles', 2);
        $this->assertCount(1, Storage::disk('local')->directories('sensors'));
    }

    private function snapshots(): void
    {
        $generation = '00000000-0000-0000-0000-000000000001';
        $disk = Storage::disk('local');
        $disk->put('sensors/index.json', json_encode(['generation' => $generation, 'sensors' => ['aoc', 'lc2kdet']], JSON_THROW_ON_ERROR));
        $jobs = [
            ['id' => 1, 'station' => 'St1', 'start' => '2026-05-25T09:00:00', 'end' => '2026-05-25T10:00:00', 'quantity' => 3],
            ['id' => 2, 'station' => 'St1', 'start' => '2026-05-25T10:15:00', 'end' => '2026-05-25T11:15:00', 'quantity' => 0],
            ['id' => 3, 'station' => 'St7', 'start' => '2026-05-27T09:00:00', 'end' => '2026-05-27T10:00:00', 'quantity' => null],
            ['id' => 4, 'station' => 'St1', 'start' => '2026-05-28T09:00:00', 'end' => '2026-05-28T10:00:00', 'quantity' => 1],
        ];
        foreach (['aoc' => $jobs, 'lc2kdet' => [$jobs[0]]] as $sensor => $records) {
            $disk->put("sensors/{$generation}/{$sensor}.json", json_encode([
                'source' => 'fixture.xlsx', 'worksheet' => $sensor.'_worklog',
                'importedAt' => '2026-11-11T12:00:00+08:00', 'jobs' => $records,
            ], JSON_THROW_ON_ERROR));
        }
    }

    private function workbook(bool $invalidSecondSheet = false): string
    {
        $path = Storage::disk('local')->path('fixture.xlsx');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $archive->addFromString('xl/workbook.xml', '<workbook xmlns="'.$namespace.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="aoc_worklog" sheetId="1" r:id="rId1"/><sheet name="lc2kdet_worklog" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
        $headers = ['id', 'StartTime', 'StopTime', 'Place', 'UnitName', 'Worker', 'SN', 'UnitTime', 'WorkTime', 'instmanual', 'skill', 'qty', 'jobcode'];
        $row = [1, 45566.375, 45566.3854166667, 'St1', 'Detector', 'Operator', '000123', 15 / 1440, 10 / 1440, 'instruction.pdf', 'NULL', 0, 0];
        $shifted = [2, 45566.5, 45566.5104166667, 'St1', 'Detector', 'Operator', 15 / 1440, 7.5 / 1440, 'instruction.pdf', 'NULL', 1, 0];
        $formula = $row;
        $formula[0] = 3;
        $formula[1] = 45566.6;
        $formula[2] = 45566.6104166667;
        foreach ([1 => [$headers, $row, $shifted, $formula], 2 => [$headers, $row]] as $sheet => $rows) {
            $xml = '<worksheet xmlns="'.$namespace.'"><sheetData>';
            foreach ($rows as $rowIndex => $values) {
                $number = $rowIndex + 1;
                $xml .= '<row r="'.$number.'">';
                foreach ($values as $column => $value) {
                    $address = chr(65 + $column).$number;
                    if ($sheet === 1 && $number === 4 && $column === 6) {
                        $xml .= '<c r="'.$address.'" t="e"><f>-INVALID_SN</f><v>#NAME?</v></c>';
                    } elseif ($invalidSecondSheet && $sheet === 2 && $number === 2 && $column === 1) {
                        $xml .= '<c r="'.$address.'" t="inlineStr"><is><t>invalid</t></is></c>';
                    } else {
                        $xml .= is_string($value)
                            ? '<c r="'.$address.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>'
                            : '<c r="'.$address.'"><v>'.$value.'</v></c>';
                    }
                }
                $xml .= '</row>';
            }
            $archive->addFromString("xl/worksheets/sheet{$sheet}.xml", $xml.'</sheetData></worksheet>');
        }
        $archive->close();

        return $path;
    }
}
