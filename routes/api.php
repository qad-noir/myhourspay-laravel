<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileSocialController;
use App\Http\Controllers\Api\MobileTimesheetController;
use App\Http\Controllers\Api\MobileWorkspaceController;
use App\Http\Controllers\Api\WorkspaceApiController;
use App\Http\Middleware\EnsureTrialChoice;
use App\Http\Middleware\MobileSession;
use App\Http\Middleware\RejectMobileToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')->middleware(['throttle:mobile-api'])->group(function (): void {
    Route::get('auth/providers', [MobileSocialController::class, 'capabilities']);
    Route::post('auth/nonce', [MobileSocialController::class, 'nonce'])->middleware('throttle:10,1,mobile-challenge');
    Route::post('auth/{provider}', [MobileSocialController::class, 'exchange'])->whereIn('provider', ['google', 'apple'])->middleware('throttle:10,1,mobile-challenge');
    Route::post('auth/providers/{provider}/link', [MobileSocialController::class, 'link'])->whereIn('provider', ['google', 'apple'])->middleware(['auth:sanctum', MobileSession::class, 'throttle:mobile-auth']);
    Route::controller(MobileAuthController::class)->group(function (): void {
        Route::post('auth/login', 'login')->middleware('throttle:mobile-auth');
        Route::post('auth/register', 'register')->middleware('throttle:mobile-auth');
        Route::post('auth/two-factor', 'twoFactor')->middleware('throttle:10,1,mobile-challenge');
        Route::post('auth/forgot-password', 'forgotPassword')->middleware('throttle:mobile-auth');
        Route::post('auth/reset-password', 'resetPassword')->middleware('throttle:mobile-auth');
        Route::middleware(['auth:sanctum', MobileSession::class.':account'])->group(function (): void {
            Route::get('me', 'me');
            Route::post('auth/email/verify', 'verify')->middleware('throttle:10,1,mobile-challenge');
            Route::post('auth/email/resend', 'resend')->middleware('throttle:3,1,mobile-resend');
            Route::delete('auth/session', 'logout');
        });
        Route::middleware(['auth:sanctum', MobileSession::class])->group(function (): void {
            Route::get('auth/sessions', 'sessions');
            Route::delete('auth/sessions/{session}', 'revoke')->whereNumber('session');
        });
    });
    Route::middleware(['auth:sanctum', MobileSession::class])->controller(MobileWorkspaceController::class)->group(function (): void {
        Route::get('workspaces', 'workspaces');
        Route::post('workspaces', 'createWorkspace');
        Route::get('workspaces/{workspace}/hours', 'hours');
        Route::post('workspaces/{workspace}/hours', 'saveHours');
        Route::patch('workspaces/{workspace}/hours/{entry}', 'saveHours');
        Route::get('workspaces/{workspace}/projects', 'projects');
    });
    Route::middleware(['auth:sanctum', MobileSession::class])->controller(MobileTimesheetController::class)->group(function (): void {
        Route::get('workspaces/{workspace}/timesheets', 'index');
        Route::get('workspaces/{workspace}/timesheets/{timesheet}', 'show');
        Route::post('workspaces/{workspace}/timesheets', 'submit');
        Route::post('workspaces/{workspace}/timesheets/{timesheet}/review', 'review');
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', RejectMobileToken::class]);

Route::prefix('v1')->middleware(['auth:sanctum', RejectMobileToken::class, 'active', EnsureTrialChoice::class, 'feature:api_access', 'throttle:premium-api'])->controller(WorkspaceApiController::class)->group(function (): void {
    Route::get('/workspaces', 'workspaces');
    Route::get('/workspaces/{workspace}/hours', 'hours');
    Route::post('/workspaces/{workspace}/hours', 'storeHours');
    Route::get('/workspaces/{workspace}/projects', 'projects');
    Route::get('/workspaces/{workspace}/invoices', 'invoices');
    Route::get('/workspaces/{workspace}/reports/hours', 'report');
    Route::post('/workspaces/{workspace}/timesheets/{timesheet}/review', 'approve');
});
