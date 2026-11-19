<?php

namespace App\Http\Controllers;

use App\Models\StationSetting;
use App\Models\User;
use App\Services\ProductionDashboard;
use App\Services\StationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StationSettingsController extends Controller
{
    public function index(Request $request, ProductionDashboard $dashboard, StationSettings $settings): View
    {
        $sensors = $dashboard->sensors();
        $input = $request->validate(['sensor' => ['sometimes', 'string', Rule::in($sensors)]]);
        $sensor = $input['sensor'] ?? (in_array(ProductionDashboard::DEFAULT_SENSOR, $sensors, true) ? ProductionDashboard::DEFAULT_SENSOR : $sensors[0]);
        $available = $settings->available();
        $stations = [];
        try {
            $stations = $dashboard->stationNames($sensor);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 503) {
                throw $exception;
            }
        }
        $selection = $request->validate(['station' => ['sometimes', 'string', Rule::in($stations)]]);
        $station = $selection['station'] ?? ($stations[0] ?? null);
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $versions = $settings->versions($sensor, '9999-12-31');
        $rows = [];
        foreach ($stations as $name) {
            $rows[] = ['station' => $name, ...$settings->resolve($sensor, $name, $today, $versions[$name] ?? [])];
        }
        $history = $available && $station !== null
            ? StationSetting::query()->where('sensor', $sensor)->where('station', $station)
                ->orderByDesc('effective_from')->orderByDesc('id')->paginate(10)->withQueryString()
            : null;

        return view('settings', [
            'sensors' => $sensors, 'sensor' => $sensor, 'stations' => $rows, 'station' => $station,
            'stationParameters' => $station === null ? null : $settings->resolve($sensor, $station, $today, $versions[$station] ?? []),
            'history' => $history, 'settingsAvailable' => $available, 'today' => $today,
        ]);
    }

    public function store(Request $request, ProductionDashboard $dashboard, StationSettings $settings): RedirectResponse
    {
        abort_unless($settings->available() && $request->user() instanceof User, 403);
        $selection = $request->validate(['sensor' => ['required', 'string', Rule::in($dashboard->sensors())]]);
        $input = $request->validate([
            'station' => ['required', 'string', Rule::in($dashboard->stationNames($selection['sensor']))],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'standard_minutes' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:99999999'],
            'working_minutes' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:1440'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        StationSetting::query()->create([
            'sensor' => $selection['sensor'],
            ...$input,
            'changed_by' => $request->user()->getAuthIdentifier(),
            'changed_by_name' => $request->user()->name,
        ]);

        return redirect()->route('settings', ['sensor' => $selection['sensor'], 'station' => $input['station']])
            ->with('station_status', 'Station parameters saved. Dashboard calculations will use this revision from its effective date.');
    }
}
