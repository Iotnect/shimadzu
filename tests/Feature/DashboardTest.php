<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductionDashboard;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_displays_the_production_workspace(): void
    {
        $this->useJobs($this->jobs());

        $this->get('/')->assertOk()->assertViewIs('dashboard')->assertSee('Production overview')->assertSee('Station activity');
    }

    public function test_daily_totals_scale_capacity_by_station_and_preserve_source_quantity(): void
    {
        $this->useJobs($this->jobs());

        $this->getJson('/dashboard/data?date=2026-05-26')
            ->assertOk()
            ->assertJsonPath('summary.cycles', 3)
            ->assertJsonPath('summary.quantity', 3)
            ->assertJsonPath('summary.available', 870)
            ->assertJsonPath('summary.running', 180)
            ->assertJsonPath('summary.idle', 15)
            ->assertJsonPath('summary.efficiency', 20.7)
            ->assertJsonPath('summary.availability', 92.3)
            ->assertJsonPath('summary.performance', 49);
    }

    public function test_station_page_displays_only_the_selected_station(): void
    {
        $this->useJobs($this->jobs());

        $response = $this->get('/stations/St1')
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertViewHas('stationPage', 'St1')
            ->assertSee('Station 1')
            ->assertSee('Open metric guide')
            ->assertSee('View all stations');

        $this->assertSame('St1', $response['dashboard']['station']);
        $this->assertSame(2, $response['dashboard']['summary']['cycles']);
        $this->assertSame(435, $response['dashboard']['summary']['available']);
        $this->assertSame(['St1'], array_unique(array_column($response['dashboard']['jobs'], 'station')));
    }

    public function test_station_data_stays_scoped_when_another_station_is_requested(): void
    {
        $this->useJobs($this->jobs());

        $this->getJson('/stations/St2/data?date=2026-05-26&station=all')
            ->assertOk()
            ->assertJsonPath('station', 'St2')
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.station', 'St2')
            ->assertJsonPath('summary.available', 435);
    }

    public function test_station_data_rejects_invalid_dates(): void
    {
        $this->actingAs(User::factory()->make());

        $this->getJson('/stations/St1/data?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_unknown_station_pages_and_data_return_404(): void
    {
        $this->actingAs(User::factory()->make());

        $this->get('/stations/St99')->assertNotFound();
        $this->getJson('/stations/St99/data?date=2026-05-26')->assertNotFound();
    }

    public function test_station_without_jobs_has_an_empty_daily_view(): void
    {
        $this->useJobs($this->jobs());

        $this->getJson('/stations/St4/data?date=2026-05-26')
            ->assertOk()
            ->assertJsonCount(0, 'jobs')
            ->assertJsonPath('summary.cycles', 0)
            ->assertJsonPath('summary.efficiency', null);
    }

    public function test_station_filter_changes_jobs_capacity_and_expected_time_together(): void
    {
        $this->useJobs($this->jobs());

        $this->getJson('/dashboard/data?date=2026-05-26&station=St1')
            ->assertOk()
            ->assertJsonCount(2, 'jobs')
            ->assertJsonCount(1, 'summary.rows')
            ->assertJsonPath('summary.available', 435)
            ->assertJsonPath('summary.running', 90)
            ->assertJsonPath('summary.quantity', 2)
            ->assertJsonPath('summary.expected', 51);
    }

    public function test_date_without_jobs_returns_unavailable_rates_and_no_activity(): void
    {
        $this->useJobs($this->jobs());

        $this->getJson('/dashboard/data?date=2026-05-24')
            ->assertOk()
            ->assertJsonCount(0, 'jobs')
            ->assertJsonPath('summary.efficiency', null)
            ->assertJsonPath('summary.availability', null)
            ->assertJsonPath('summary.performance', null);
    }

    public function test_overlapping_jobs_do_not_double_count_running_minutes(): void
    {
        $jobs = $this->jobs();
        $jobs[1]['start'] = '2026-05-26T09:30:00';
        $jobs[1]['end'] = '2026-05-26T10:15:00';
        $this->useJobs($jobs);

        $this->getJson('/dashboard/data?date=2026-05-26&station=St1')
            ->assertOk()
            ->assertJsonPath('summary.running', 75)
            ->assertJsonPath('summary.idle', 0);
    }

    public function test_missing_standard_and_quantity_remain_visible(): void
    {
        $jobs = [$this->jobs()[0]];
        $jobs[0]['station'] = 'St5';
        $jobs[0]['quantity'] = null;
        $this->useJobs($jobs);

        $this->getJson('/dashboard/data?date=2026-05-26&station=St5')
            ->assertOk()
            ->assertJsonPath('summary.performance', null)
            ->assertJsonPath('summary.expected', null)
            ->assertJsonPath('summary.missingQuantity', 1)
            ->assertJsonPath('jobs.0.quantity', null);
    }

    public function test_invalid_date_returns_422_with_a_field_error(): void
    {
        $this->actingAs(User::factory()->make());

        $this->getJson('/dashboard/data?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date')
            ->assertJsonPath('errors.date.0', 'The date field must match the format Y-m-d.');
    }

    public function test_unrecognized_station_returns_422_with_a_field_error(): void
    {
        $this->actingAs(User::factory()->make());

        $this->getJson('/dashboard/data?date=2026-05-26&station=St99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('station')
            ->assertJsonPath('errors.station.0', 'The selected station is invalid.');
    }

    /** @return array<int, array<string, mixed>> */
    private function jobs(): array
    {
        return [
            ['id' => 1, 'station' => 'St1', 'start' => '2026-05-26T09:00:00', 'end' => '2026-05-26T10:00:00', 'quantity' => 2],
            ['id' => 2, 'station' => 'St1', 'start' => '2026-05-26T10:15:00', 'end' => '2026-05-26T10:45:00', 'quantity' => 0],
            ['id' => 3, 'station' => 'St2', 'start' => '2026-05-26T09:00:00', 'end' => '2026-05-26T10:30:00', 'quantity' => 1],
        ];
    }

    /** @param array<int, array<string, mixed>> $jobs */
    private function useJobs(array $jobs): void
    {
        $this->actingAs(User::factory()->make());

        $this->app->instance(ProductionDashboard::class, new class($jobs) extends ProductionDashboard
        {
            public function __construct(private array $jobs) {}

            public function snapshot(): array
            {
                return ['source' => 'fixture.xlsx', 'importedAt' => '2026-10-21T12:00:00+08:00', 'jobs' => $this->jobs];
            }
        });
    }
}
