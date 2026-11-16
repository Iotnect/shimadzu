<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/setting', [StationSettingsController::class, 'index'])->name('settings');
    Route::post('/setting/stations', [StationSettingsController::class, 'store'])->name('settings.stations.store');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/sensors/{sensor}', [DashboardController::class, 'sensor'])
        ->where('sensor', '[a-z0-9]+')
        ->name('sensors.show');
    Route::get('/sensors/{sensor}/data', [DashboardController::class, 'sensorData'])
        ->where('sensor', '[a-z0-9]+')
        ->name('sensors.data');
    Route::get('/stations/{station}', [DashboardController::class, 'station'])
        ->whereIn('station', ['St1', 'St2', 'St3', 'St4'])
        ->name('stations.show');
    Route::get('/stations/{station}/data', [DashboardController::class, 'stationData'])
        ->whereIn('station', ['St1', 'St2', 'St3', 'St4'])
        ->name('stations.data');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
