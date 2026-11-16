<?php

namespace App\Http\Controllers;

use App\Services\ProductionDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProductionDashboard $dashboard): View
    {
        $filters = $this->filters($request, $dashboard);
        $filters['station'] = 'all';

        return view('dashboard', ['dashboard' => $dashboard->overview(...$filters)]);
    }

    public function data(Request $request, ProductionDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->overview(...$this->filters($request, $dashboard)));
    }

    public function sensor(Request $request, ProductionDashboard $dashboard, string $sensor): View
    {
        abort_unless(in_array($sensor, $dashboard->sensors(), true), 404);
        $filters = $this->filters($request, $dashboard);
        $filters['sensor'] = $sensor;

        return view('dashboard', ['dashboard' => $dashboard->overview(...$filters), 'sensorPage' => $sensor]);
    }

    public function sensorData(Request $request, ProductionDashboard $dashboard, string $sensor): JsonResponse
    {
        abort_unless(in_array($sensor, $dashboard->sensors(), true), 404);
        $filters = $this->filters($request, $dashboard);
        $filters['sensor'] = $sensor;

        return response()->json($dashboard->overview(...$filters));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, ProductionDashboard $dashboard): array
    {
        return $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'from' => ['required_with:to', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sensor' => ['sometimes', 'string', Rule::in($dashboard->sensors())],
            'station' => ['sometimes', 'string', Rule::in(['all', 'St1', 'St2', 'St3', 'St4', 'St5', 'St6', 'St7'])],
        ]);
    }

    public function station(Request $request, ProductionDashboard $dashboard, string $station): View
    {
        $filters = $this->filters($request, $dashboard);
        $filters['station'] = $station;

        return view('dashboard', [
            'dashboard' => $dashboard->overview(...$filters),
            'stationPage' => $station,
        ]);
    }

    public function stationData(Request $request, ProductionDashboard $dashboard, string $station): JsonResponse
    {
        $filters = $this->filters($request, $dashboard);
        $filters['station'] = $station;

        return response()->json($dashboard->overview(...$filters));
    }
}
