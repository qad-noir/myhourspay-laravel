<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\HoursSettingsController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/workspaces/onboarding', [WorkspaceController::class, 'onboarding'])->name('workspaces.onboarding');
    Route::get('/workspaces/name-availability', [WorkspaceController::class, 'availability'])->name('workspaces.name-availability');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::middleware('workspace')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::put('/settings/hours', [HoursSettingsController::class, 'update'])->name('settings.hours.update');

        Route::prefix('hours')->name('hours.')->controller(HoursController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/events', 'events')->name('events');
            Route::post('/entries', 'store')->name('entries.store');
            Route::patch('/entries/{hoursEntry}', 'update')->name('entries.update');
            Route::delete('/entries/{hoursEntry}', 'destroy')->name('entries.destroy');
            Route::get('/reports', 'report')->name('reports.index');
            Route::get('/reports/export/excel', 'excel')->name('reports.excel');
            Route::get('/reports/export/csv', 'csv')->name('reports.csv');
            Route::get('/reports/print', 'print')->name('reports.print');
        });
    });
});
