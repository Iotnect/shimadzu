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
