<?php

namespace App\Http\Controllers;

use App\Services\ProductionDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ProductionDashboard $dashboard): View
    {
        return view('dashboard', ['dashboard' => $dashboard->overview()]);
    }

    public function data(Request $request, ProductionDashboard $dashboard): JsonResponse
    {
        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'station' => ['sometimes', 'string', Rule::in(['all', 'St1', 'St2', 'St3', 'St4', 'St5'])],
        ]);

        return response()->json($dashboard->overview($input['date'], $input['station'] ?? 'all'));
    }
}
