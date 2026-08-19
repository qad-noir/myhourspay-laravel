<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\HoursSettingsController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDataController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AdminOperationsController;
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
        Route::get('/users/{user}', 'user')->whereNumber('user')->name('users.show');
        Route::put('/users/{user}', 'updateUser')->whereNumber('user')->name('users.update');
        Route::post('/users/{user}/suspension', 'suspend')->whereNumber('user')->name('users.suspension');
        Route::get('/workspaces', 'workspaces')->name('workspaces.index');
        Route::get('/workspaces/{workspace}', 'workspace')->whereNumber('workspace')->name('workspaces.show');
        Route::put('/workspaces/{workspace}', 'updateWorkspace')->whereNumber('workspace')->name('workspaces.update');
    });
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::controller(AdminManagementController::class)->group(function (): void {
            Route::get('/users/create','createUser')->name('users.create'); Route::post('/users','storeUser')->name('users.store');
            Route::post('/users/{user}/verification','verify')->name('users.verification'); Route::post('/users/{user}/verification/resend','resendVerification')->name('users.verification.resend'); Route::post('/users/{user}/workspace-reset','resetWorkspace')->name('users.workspace-reset'); Route::delete('/users/{user}','deleteUser')->name('users.destroy'); Route::post('/trash/users/{user}/restore','restoreUser')->name('users.restore'); Route::delete('/trash/users/{user}','forceDeleteUser')->name('users.force-delete');
            Route::get('/workspaces/create','createWorkspace')->name('workspaces.create'); Route::post('/workspaces','storeWorkspace')->name('workspaces.store'); Route::delete('/workspaces/{workspace}','deleteWorkspace')->name('workspaces.destroy'); Route::post('/trash/workspaces/{workspace}/restore','restoreWorkspace')->name('workspaces.restore'); Route::delete('/trash/workspaces/{workspace}','forceDeleteWorkspace')->name('workspaces.force-delete');
            Route::get('/hours','hours')->name('hours.index'); Route::get('/hours/create','createHours')->name('hours.create'); Route::post('/hours','storeHours')->name('hours.store'); Route::get('/hours/{hoursEntry}/edit','editHours')->name('hours.edit'); Route::put('/hours/{hoursEntry}','updateHours')->name('hours.update'); Route::delete('/hours/{hoursEntry}','deleteHours')->name('hours.destroy'); Route::post('/trash/hours/{trashedHoursEntry}/restore','restoreHours')->name('hours.restore'); Route::delete('/trash/hours/{trashedHoursEntry}','forceDeleteHours')->name('hours.force-delete'); Route::get('/trash','trash')->name('trash');
        });
        Route::controller(AdminOperationsController::class)->group(function (): void { Route::get('/audit-logs','audits')->name('audit-logs.index'); Route::get('/audit-logs/{auditLog}','audit')->name('audit-logs.show'); Route::get('/incidents','incidents')->name('incidents.index'); Route::get('/incidents/{incident}','incident')->name('incidents.show'); Route::post('/incidents/{incident}/resolve','resolve')->name('incidents.resolve'); Route::post('/incidents/{incident}/reopen','reopen')->name('incidents.reopen'); });
        Route::prefix('data')->name('data.')->controller(AdminDataController::class)->group(function (): void { Route::get('/users','users')->name('users'); Route::get('/workspaces','workspaces')->name('workspaces'); Route::get('/hours','hours')->name('hours'); Route::get('/audit-logs','audits')->name('audit-logs'); Route::get('/incidents','incidents')->name('incidents'); });
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
