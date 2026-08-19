<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\HoursSettingsController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/logout', function (Request $request) {
    if (! $request->user()) {
        return redirect()->route('login');
    }

    return redirect()->back()->with('status', 'Use the Log out button to sign out securely.');
})->name('logout.help');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'active',
])->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('admin')->controller(AdminController::class)->group(function (): void {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/users', 'users')->name('users.index');
        Route::get('/users/{user}', 'user')->name('users.show');
        Route::put('/users/{user}', 'updateUser')->name('users.update');
        Route::post('/users/{user}/suspension', 'suspend')->name('users.suspension');
        Route::get('/workspaces', 'workspaces')->name('workspaces.index');
        Route::get('/workspaces/{workspace}', 'workspace')->name('workspaces.show');
        Route::put('/workspaces/{workspace}', 'updateWorkspace')->name('workspaces.update');
    });
    Route::get('/verify-email-code', [EmailVerificationCodeController::class, 'show'])->name('email-code.show');
    Route::post('/verify-email-code', [EmailVerificationCodeController::class, 'verify'])->middleware('throttle:10,1')->name('email-code.verify');
    Route::post('/verify-email-code/resend', [EmailVerificationCodeController::class, 'resend'])->middleware('throttle:6,1')->name('email-code.resend');

    Route::middleware('email-code.verified')->group(function (): void {
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
});
