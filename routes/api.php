<?php

use App\Http\Controllers\Api\WorkspaceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum', 'active', 'feature:api_access', 'throttle:premium-api'])->controller(WorkspaceApiController::class)->group(function (): void {
    Route::get('/workspaces', 'workspaces');
    Route::get('/workspaces/{workspace}/hours', 'hours');
    Route::post('/workspaces/{workspace}/hours', 'storeHours');
    Route::get('/workspaces/{workspace}/projects', 'projects');
    Route::get('/workspaces/{workspace}/invoices', 'invoices');
    Route::get('/workspaces/{workspace}/reports/hours', 'report');
    Route::post('/workspaces/{workspace}/timesheets/{timesheet}/review', 'approve');
});
