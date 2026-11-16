<?php

namespace Tests\Feature;

use App\Models\StationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StationSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = User::factory()->create();
        $this->actingAs($this->administrator);
        Storage::fake('local');
        config(['production.sensor_worklog_directory' => Storage::disk('local')->path('sensors')]);
        $generation = '00000000-0000-0000-0000-000000000001';
        Storage::disk('local')->put('sensors/index.json', json_encode(['generation' => $generation, 'sensors' => ['lc2kdet', 'aoc']], JSON_THROW_ON_ERROR));
        foreach (['lc2kdet', 'aoc'] as $sensor) {
            $jobs = [];
            foreach (['2026-05-31', '2026-06-01'] as $date) {
                foreach ($sensor === 'lc2kdet' ? ['St1', 'St2'] : ['St1'] as $station) {
                    $jobs[] = ['id' => count($jobs) + 1, 'station' => $station, 'start' => $date.'T09:00:00', 'end' => $date.'T10:00:00', 'quantity' => 1];
                }
            }
            Storage::disk('local')->put("sensors/{$generation}/{$sensor}.json", json_encode([
                'source' => 'fixture.xlsx', 'importedAt' => '2026-11-11T12:00:00+08:00', 'jobs' => $jobs,
            ], JSON_THROW_ON_ERROR));
        }
    }

    public function test_station_settings_are_above_appearance_and_only_list_the_selected_sensors_stations(): void
    {
        $this->get('/setting?sensor=aoc')->assertOk()
            ->assertSeeInOrder(['Station Settings', 'Appearance'])
            ->assertViewHas('station', 'St1')
            ->assertViewHas('stations', fn (array $rows): bool => array_column($rows, 'station') === ['St1']);
    }

    public function test_saving_a_revision_records_the_author_and_preserves_previous_revisions(): void
    {
        $input = $this->parameters();
        $this->post('/setting/stations', $input)->assertRedirect(route('settings', ['sensor' => 'lc2kdet', 'station' => 'St1']));
        $this->post('/setting/stations', [...$input, 'standard_minutes' => 40, 'notes' => 'Second meeting decision.'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('station_settings', 2);
        $this->assertDatabaseHas('station_settings', ['standard_minutes' => 30, 'changed_by' => $this->administrator->id, 'notes' => 'Meeting decision.']);
        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St1&date=2026-06-01')
            ->assertOk()->assertJsonPath('summary.rows.0.standard', 40)->assertJsonPath('summary.expected', 40);
        $this->get('/setting?sensor=lc2kdet&station=St1')->assertOk()
            ->assertSeeInOrder(['Second meeting decision.', 'Meeting decision.']);
    }

    public function test_effective_dates_change_only_the_selected_sensor_and_station(): void
    {
        $this->post('/setting/stations', $this->parameters())->assertSessionHasNoErrors();

        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St1&date=2026-05-31')
            ->assertOk()->assertJsonPath('summary.available', 435)->assertJsonPath('summary.rows.0.standard', 25.515);
        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St1&date=2026-06-01')
            ->assertOk()->assertJsonPath('summary.available', 450)->assertJsonPath('summary.rows.0.standard', 30)
            ->assertJsonPath('summary.performance', 50)->assertJsonPath('source.standardSource', 'Station Settings');
        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St2&date=2026-06-01')
            ->assertOk()->assertJsonPath('summary.available', 435)->assertJsonPath('summary.rows.0.standard', 37.24);
        $this->getJson('/dashboard/data?sensor=aoc&station=St1&date=2026-06-01')
            ->assertOk()->assertJsonPath('summary.available', 435)->assertJsonPath('summary.performance', null);
    }

    public function test_a_date_range_sums_each_days_capacity_and_each_cycles_effective_standard(): void
    {
        StationSetting::factory()->create(['effective_from' => '2026-06-01']);

        $this->getJson('/dashboard/data?sensor=lc2kdet&from=2026-05-31&to=2026-06-01')
            ->assertOk()->assertJsonPath('summary.available', 1755)->assertJsonPath('summary.expected', 130)
            ->assertJsonPath('summary.running', 240)->assertJsonPath('summary.performance', 54.2)
            ->assertJsonPath('summary.rows.0.standard', null)->assertJsonPath('summary.rows.0.standardVaries', true)
            ->assertJsonPath('summary.rows.0.available', 885)->assertJsonPath('summary.rows.1.available', 870);
    }

    public function test_a_future_revision_does_not_affect_earlier_work_dates(): void
    {
        StationSetting::factory()->create(['effective_from' => '2026-06-02']);

        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St1&date=2026-06-01')
            ->assertOk()->assertJsonPath('summary.available', 435)->assertJsonPath('summary.rows.0.standard', 25.515);
    }

    public function test_a_new_sensor_standard_enables_performance_but_missing_historical_standards_remain_unavailable(): void
    {
        StationSetting::factory()->create(['sensor' => 'aoc', 'effective_from' => '2026-06-01']);

        $this->getJson('/dashboard/data?sensor=aoc&date=2026-06-01')->assertOk()
            ->assertJsonPath('summary.performance', 50)->assertJsonPath('summary.expected', 30);
        $this->getJson('/dashboard/data?sensor=aoc&from=2026-05-31&to=2026-06-01')->assertOk()
            ->assertJsonPath('summary.expected', null)->assertJsonPath('summary.performance', null);
    }

    public function test_empty_days_and_explicitly_unconfigured_standards_are_supported(): void
    {
        $this->post('/setting/stations', [...$this->parameters(), 'standard_minutes' => '', 'working_minutes' => '400.5'])
            ->assertSessionHasNoErrors();

        $this->getJson('/dashboard/data?sensor=lc2kdet&station=St1&date=2026-06-02')
            ->assertOk()->assertJsonPath('summary.cycles', 0)->assertJsonPath('summary.available', 400.5)
            ->assertJsonPath('summary.performance', null);
    }

    public function test_invalid_station_parameters_are_rejected_without_creating_revisions(): void
    {
        foreach ([
            ['station', 'St7'], ['sensor', 'unknown'], ['effective_from', 'invalid'],
            ['working_minutes', 0], ['working_minutes', 1441], ['standard_minutes', -1],
            ['standard_minutes', '1.12345'], ['notes', ''],
        ] as [$field, $value]) {
            $this->post('/setting/stations', [...$this->parameters(), $field => $value])
                ->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('station_settings', 0);
    }

    public function test_guests_and_environment_accounts_cannot_change_station_parameters(): void
    {
        config(['auth.providers.users.driver' => 'environment']);
        $this->post('/setting/stations', $this->parameters())->assertForbidden();
        Auth::forgetGuards();
        $this->get('/setting')->assertRedirect(route('login'));
        $this->post('/setting/stations', $this->parameters())->assertRedirect(route('login'));
        $this->assertDatabaseCount('station_settings', 0);
    }

    /** @return array<string, mixed> */
    private function parameters(): array
    {
        return [
            'sensor' => 'lc2kdet', 'station' => 'St1',
            'effective_from' => '2026-06-01', 'standard_minutes' => 30, 'working_minutes' => 450,
            'notes' => 'Meeting decision.',
        ];
    }
}
