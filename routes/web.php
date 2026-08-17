<?php

use App\Http\Controllers\HoursController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

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
