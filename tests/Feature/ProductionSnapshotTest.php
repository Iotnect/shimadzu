<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['production.worklog_url' => null, 'production.worklog_token' => null, 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    public function test_the_dashboard_reads_a_configured_local_snapshot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('snapshot.json', json_encode($this->snapshot(), JSON_THROW_ON_ERROR));
        config(['production.worklog_path' => Storage::disk('local')->path('snapshot.json')]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')
            ->assertOk()->assertJsonPath('summary.cycles', 1)->assertJsonPath('source.name', 'fixture.xlsx');
    }

    public function test_a_private_remote_snapshot_is_authenticated_and_cached(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json', 'production.worklog_token' => 'private-test-token']);
        Http::fake(['https://storage.example.test/*' => Http::response($this->snapshot())]);
        $this->actingAs(User::factory()->make());

        $this->getJson('/dashboard/data?date=2026-05-26')->assertOk()->assertJsonPath('summary.quantity', 2);
        $this->getJson('/dashboard/data?date=2026-05-26&station=St1')->assertOk()->assertJsonCount(1, 'jobs');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer private-test-token'));
        Http::assertSentCount(1);
    }

    public function test_the_dashboard_selects_lines_from_the_remote_workbook_catalog(): void
    {
        $origin = 'https://worklogs.example.test';
        $generation = '92f3238d-c0d9-4979-9f09-c80303270a40';
        config(['production.worklog_origin_url' => $origin, 'production.worklog_origin_token' => 'origin-test-token']);
        Http::fake([
            $origin.'/index.json' => Http::response(['generation' => $generation, 'sensors' => ['lc2kdet', 'aoc']]),
            $origin.'/'.$generation.'/lc2kdet.json' => Http::response($this->snapshot()),
            $origin.'/'.$generation.'/aoc.json' => Http::response(array_replace($this->snapshot(), ['worksheet' => 'aoc_worklog'])),
        ]);

        $this->actingAs(User::factory()->make())
            ->getJson('/dashboard/data?date=2026-05-26')
            ->assertOk()->assertJsonPath('sensors', ['lc2kdet', 'aoc'])->assertJsonPath('sensor', 'lc2kdet');
        $this->getJson('/sensors/aoc/data?date=2026-05-26')
            ->assertOk()->assertJsonPath('sensor', 'aoc')->assertJsonPath('source.worksheet', 'aoc_worklog');

        Http::assertSent(fn (Request $request): bool => $request->url() === $origin.'/'.$generation.'/aoc.json'
            && $request->hasHeader('Authorization', 'Bearer origin-test-token'));
    }

    public function test_an_insecure_worklog_origin_is_rejected_without_sending_credentials(): void
    {
        config(['production.worklog_origin_url' => 'http://worklogs.example.test', 'production.worklog_origin_token' => 'origin-test-token']);
        Http::fake();

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data')
            ->assertServiceUnavailable()->assertJsonPath('message', 'Production data is temporarily unavailable.');

        Http::assertNothingSent();
    }

    public function test_a_worklog_origin_without_a_token_does_not_send_a_request(): void
    {
        config(['production.worklog_origin_url' => 'https://worklogs.example.test', 'production.worklog_origin_token' => null]);
        Http::fake();

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data')->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_a_rejected_worklog_origin_response_does_not_expose_private_details(): void
    {
        config(['production.worklog_origin_url' => 'https://worklogs.example.test', 'production.worklog_origin_token' => 'origin-test-token']);
        Http::fake(['https://worklogs.example.test/index.json' => Http::response('Private upstream error', 401)]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data')
            ->assertServiceUnavailable()->assertJsonPath('message', 'Production data is temporarily unavailable.')
            ->assertDontSee('Private upstream error')->assertDontSee('origin-test-token');
    }

    public function test_a_worklog_origin_redirect_is_not_followed(): void
    {
        config(['production.worklog_origin_url' => 'https://worklogs.example.test', 'production.worklog_origin_token' => 'origin-test-token']);
        Http::fake(['https://worklogs.example.test/index.json' => Http::response('', 302, ['Location' => 'https://other.example.test/index.json'])]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data')->assertServiceUnavailable();

        Http::assertSentCount(1);
    }

    public function test_a_worklog_origin_connection_failure_returns_503(): void
    {
        config(['production.worklog_origin_url' => 'https://worklogs.example.test', 'production.worklog_origin_token' => 'origin-test-token']);
        Http::fake(['https://worklogs.example.test/index.json' => Http::failedConnection()]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data')
            ->assertServiceUnavailable()->assertJsonPath('message', 'Production data is temporarily unavailable.');
    }

    public function test_the_bundled_snapshot_loads_when_runtime_storage_is_relocated(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('deployment/storage/app/private/det-worklog.json', json_encode($this->snapshot(), JSON_THROW_ON_ERROR));
        $basePath = $this->app->basePath();
        $storagePath = $this->app->storagePath();

        try {
            $this->app->setBasePath(Storage::disk('local')->path('deployment'));
            $this->app->useStoragePath(Storage::disk('local')->path('runtime'));
            config(['production' => require $basePath.'/config/production.php']);
        } finally {
            $this->app->setBasePath($basePath);
            $this->app->useStoragePath($storagePath);
        }

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')
            ->assertOk()->assertJsonPath('source.isDemo', false)->assertJsonPath('source.name', 'fixture.xlsx')
            ->assertJsonPath('summary.quantity', 2);
    }

    public function test_the_private_snapshot_cannot_be_downloaded(): void
    {
        $this->actingAs(User::factory()->make())->get('/storage/app/private/det-worklog.json')->assertForbidden();
        $this->get('/storage/det-worklog.json')->assertForbidden();
        $this->get('/det-worklog.json')->assertNotFound();
    }

    public function test_a_signed_snapshot_url_can_be_used_without_a_bearer_token(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json?signature=example']);
        Http::fake(['https://storage.example.test/*' => Http::response($this->snapshot())]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertOk();

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    }

    public function test_an_insecure_snapshot_url_is_rejected_without_an_outbound_request(): void
    {
        config(['production.worklog_url' => 'http://storage.example.test/snapshot.json']);
        Http::fake();

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_an_unavailable_remote_snapshot_returns_a_generic_503(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json?signature=secret']);
        Http::fake(['https://storage.example.test/*' => Http::response('Private upstream error', 403)]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')
            ->assertServiceUnavailable()->assertJsonPath('message', 'Production data is temporarily unavailable.')
            ->assertDontSee('Private upstream error')->assertDontSee('signature=secret');
    }

    public function test_a_remote_redirect_is_rejected(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json']);
        Http::fake(['https://storage.example.test/*' => Http::response('', 302, ['Location' => 'https://other.example.test/snapshot.json'])]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();

        Http::assertSentCount(1);
    }

    public function test_invalid_remote_json_returns_503(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json']);
        Http::fake(['https://storage.example.test/*' => Http::response('not json')]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();
    }

    public function test_a_snapshot_without_jobs_returns_503(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json']);
        Http::fake(['https://storage.example.test/*' => Http::response(['source' => 'fixture.xlsx', 'importedAt' => '2026-10-26T09:00:00+08:00', 'jobs' => []])]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();
    }

    public function test_a_connection_failure_returns_503(): void
    {
        config(['production.worklog_url' => 'https://storage.example.test/snapshot.json']);
        Http::fake(['https://storage.example.test/*' => Http::failedConnection()]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();
    }

    public function test_a_missing_local_snapshot_returns_503(): void
    {
        Storage::fake('local');
        config(['production.worklog_path' => Storage::disk('local')->path('missing.json')]);

        $this->actingAs(User::factory()->make())->getJson('/dashboard/data?date=2026-05-26')->assertServiceUnavailable();
    }

    public function test_a_missing_worklog_keeps_the_workspace_navigation_available(): void
    {
        Storage::fake('local');
        config(['production.worklog_path' => Storage::disk('local')->path('missing.json')]);

        $this->actingAs(User::factory()->make())->get('/')
            ->assertServiceUnavailable()
            ->assertSee('Production worklog unavailable')
            ->assertSee('Settings')
            ->assertSee('Sign out')
            ->assertDontSee('Open metric guide');
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'source' => 'fixture.xlsx',
            'importedAt' => '2026-10-26T09:00:00+08:00',
            'jobs' => [
                ['id' => 1, 'station' => 'St1', 'start' => '2026-05-26T09:00:00', 'end' => '2026-05-26T09:30:00', 'quantity' => 2],
            ],
        ];
    }
}
