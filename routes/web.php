<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/setting', 'settings')->name('settings');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/stations/{station}', [DashboardController::class, 'station'])
        ->whereIn('station', ['St1', 'St2', 'St3', 'St4'])
        ->name('stations.show');
    Route::get('/stations/{station}/data', [DashboardController::class, 'stationData'])
        ->whereIn('station', ['St1', 'St2', 'St3', 'St4'])
        ->name('stations.data');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
