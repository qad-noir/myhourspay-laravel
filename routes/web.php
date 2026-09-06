<?php

use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminBillingDataController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDataController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AdminOperationsController;
use App\Http\Controllers\Admin\AdminOptionController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessToolsController;
use App\Http\Controllers\CalendarIntegrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\HoursController;
use App\Http\Controllers\HoursSettingsController;
use App\Http\Controllers\ProController;
use App\Http\Controllers\ProToolsController;
use App\Http\Controllers\WorkspaceController;
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

Route::get('/business/invitations/{invitation}/accept', [BusinessController::class, 'acceptInvitation'])
    ->middleware('active')
    ->name('business.invitations.accept');

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
            Route::get('/users/create', 'createUser')->name('users.create');
            Route::post('/users', 'storeUser')->name('users.store');
            Route::post('/users/{user}/verification', 'verify')->name('users.verification');
            Route::post('/users/{user}/verification/resend', 'resendVerification')->name('users.verification.resend');
            Route::post('/users/{user}/workspace-reset', 'resetWorkspace')->name('users.workspace-reset');
            Route::delete('/users/{user}', 'deleteUser')->name('users.destroy');
            Route::post('/trash/users/{user}/restore', 'restoreUser')->name('users.restore');
            Route::delete('/trash/users/{user}', 'forceDeleteUser')->name('users.force-delete');
            Route::get('/workspaces/create', 'createWorkspace')->name('workspaces.create');
            Route::post('/workspaces', 'storeWorkspace')->name('workspaces.store');
            Route::delete('/workspaces/{workspace}', 'deleteWorkspace')->name('workspaces.destroy');
            Route::post('/trash/workspaces/{workspace}/restore', 'restoreWorkspace')->name('workspaces.restore');
            Route::delete('/trash/workspaces/{workspace}', 'forceDeleteWorkspace')->name('workspaces.force-delete');
            Route::get('/hours', 'hours')->name('hours.index');
            Route::get('/hours/create', 'createHours')->name('hours.create');
            Route::post('/hours', 'storeHours')->name('hours.store');
            Route::get('/hours/{hoursEntry}/edit', 'editHours')->name('hours.edit');
            Route::put('/hours/{hoursEntry}', 'updateHours')->name('hours.update');
            Route::delete('/hours/{hoursEntry}', 'deleteHours')->name('hours.destroy');
            Route::post('/trash/hours/{trashedHoursEntry}/restore', 'restoreHours')->name('hours.restore');
            Route::delete('/trash/hours/{trashedHoursEntry}', 'forceDeleteHours')->name('hours.force-delete');
            Route::get('/trash', 'trash')->name('trash');
        });
        Route::controller(AdminOperationsController::class)->group(function (): void {
            Route::get('/audit-logs', 'audits')->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', 'audit')->name('audit-logs.show');
            Route::get('/incidents', 'incidents')->name('incidents.index');
            Route::get('/incidents/{incident}', 'incident')->name('incidents.show');
            Route::post('/incidents/{incident}/resolve', 'resolve')->name('incidents.resolve');
            Route::post('/incidents/{incident}/reopen', 'reopen')->name('incidents.reopen');
        });
        Route::prefix('data')->name('data.')->controller(AdminDataController::class)->group(function (): void {
            Route::get('/users', 'users')->name('users');
            Route::get('/workspaces', 'workspaces')->name('workspaces');
            Route::get('/hours', 'hours')->name('hours');
            Route::get('/audit-logs', 'audits')->name('audit-logs');
            Route::get('/incidents', 'incidents')->name('incidents');
        });
        Route::prefix('options')->name('options.')->controller(AdminOptionController::class)->group(function (): void {
            Route::get('/users', 'users')->name('users');
            Route::get('/users/{user}/workspaces', 'workspaces')->whereNumber('user')->name('user-workspaces');
        });
        Route::prefix('billing')->name('billing.')->controller(AdminBillingController::class)->group(function (): void {
            Route::get('/', 'overview')->name('overview');
            Route::put('/switches', 'updateSwitch')->name('switches.update');
            Route::get('/features', 'features')->name('features');
            Route::put('/features/{feature}', 'updateFeature')->name('features.update');
            Route::get('/plans', 'plans')->name('plans');
            Route::put('/plans/{plan}/prices/{planPrice}', 'updatePlanPrice')->name('plans.prices.update');
            Route::put('/plans/{plan}/features/{feature}', 'updatePlanFeature')->name('plans.features.update');
            Route::get('/subscribers', 'subscribers')->name('subscribers');
            Route::post('/subscribers/{user}/resync', 'resync')->name('subscribers.resync');
            Route::post('/subscribers/{user}/cancel', 'cancelSubscriber')->name('subscribers.cancel');
            Route::get('/grants', 'grants')->name('grants');
            Route::get('/grants/create', 'createGrant')->name('grants.create');
            Route::post('/grants', 'storeGrant')->name('grants.store');
            Route::post('/grants/{grant}/revoke', 'revokeGrant')->name('grants.revoke');
            Route::get('/health', 'health')->name('health');
        });
        Route::prefix('data/billing')->name('data.billing.')->controller(AdminBillingDataController::class)->group(function (): void {
            Route::get('/subscribers', 'subscribers')->name('subscribers');
            Route::get('/grants', 'grants')->name('grants');
            Route::get('/webhooks', 'webhooks')->name('webhooks');
        });
        Route::prefix('support')->name('support.')->controller(AdminSupportController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/{supportRequest}', 'show')->name('show');
            Route::put('/{supportRequest}', 'update')->name('update');
        });
    });
    Route::get('/verify-email-code', [EmailVerificationCodeController::class, 'show'])->name('email-code.show');
    Route::post('/verify-email-code', [EmailVerificationCodeController::class, 'verify'])->middleware('throttle:10,1')->name('email-code.verify');
    Route::post('/verify-email-code/resend', [EmailVerificationCodeController::class, 'resend'])->middleware('throttle:6,1')->name('email-code.resend');

    Route::middleware('email-code.verified')->group(function (): void {
        Route::get('/workspaces/onboarding', [WorkspaceController::class, 'onboarding'])->name('workspaces.onboarding');
        Route::get('/workspaces/name-availability', [WorkspaceController::class, 'availability'])->name('workspaces.name-availability');
        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::prefix('billing')->name('billing.')->controller(BillingController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/checkout', 'checkout')->middleware('throttle:6,1')->name('checkout');
            Route::post('/sync', 'sync')->middleware('throttle:6,1')->name('sync');
            Route::post('/portal', 'portal')->name('portal');
            Route::post('/change', 'change')->name('change');
            Route::post('/cancel', 'cancel')->name('cancel');
            Route::post('/resume', 'resume')->name('resume');
            Route::get('/success', 'success')->name('success');
            Route::get('/cancelled', 'cancelled')->name('cancelled');
        });

        Route::middleware('workspace')->group(function (): void {

            Route::get('/dashboard', DashboardController::class)->name('dashboard');
            Route::get('/business', [BusinessToolsController::class, 'overview'])->name('business.index');
            Route::prefix('business')->name('business.')->controller(BusinessController::class)->group(function (): void {
                Route::post('/invitations', 'invite')->middleware('feature:team_members')->name('invitations.store');
                Route::put('/members/{member}', 'updateMember')->middleware('feature:roles_permissions')->name('members.update');
                Route::delete('/members/{member}', 'removeMember')->middleware('feature:team_members')->name('members.destroy');
                Route::post('/timesheets', 'submitTimesheet')->middleware('feature:timesheet_approvals')->name('timesheets.submit');
                Route::post('/timesheets/{timesheet}/review', 'reviewTimesheet')->middleware('feature:timesheet_approvals')->name('timesheets.review');
                Route::post('/leave/types', 'storeLeaveType')->middleware('feature:leave_tracking')->name('leave-types.store');
                Route::post('/leave', 'requestLeave')->middleware('feature:leave_tracking')->name('leave.store');
                Route::post('/leave/{leaveRequest}/review', 'reviewLeave')->middleware('feature:leave_tracking')->name('leave.review');
                Route::post('/payroll/profiles', 'storePayrollProfile')->middleware('feature:payroll_exports')->name('payroll-profiles.store');
                Route::get('/payroll/{profile}', 'payroll')->middleware('feature:payroll_exports')->name('payroll.download');
                Route::put('/branding', 'updateBranding')->middleware('feature:custom_branding')->name('branding.update');
                Route::post('/support', 'support')->middleware('feature:priority_support')->name('support.store');
                Route::post('/webhooks', 'storeWebhook')->middleware('feature:outbound_webhooks')->name('webhooks.store');
                Route::delete('/webhooks/{endpoint}', 'deleteWebhook')->middleware('feature:outbound_webhooks')->name('webhooks.destroy');
            });
            Route::prefix('business')->name('business.')->controller(BusinessToolsController::class)->group(function (): void {
                Route::get('/team', 'team')->name('team.index');
                Route::get('/timesheets', 'timesheets')->name('timesheets.index');
                Route::get('/leave', 'leave')->name('leave.index');
                Route::get('/payroll', 'payroll')->name('payroll.index');
                Route::get('/branding', 'branding')->name('branding.index');
                Route::get('/activity', 'activity')->name('activity.index');
                Route::get('/webhooks', 'webhooks')->name('webhooks.index');
                Route::get('/support', 'support')->name('support.index');
            });
            Route::get('/pro', [ProToolsController::class, 'overview'])->name('pro.index');
            Route::prefix('pro')->name('pro.')->controller(ProController::class)->group(function (): void {
                Route::post('/clients', 'storeClient')->middleware(['feature:clients_projects', 'workspace.writable'])->name('clients.store');
                Route::put('/clients/{client}', 'updateClient')->middleware(['feature:clients_projects', 'workspace.writable'])->name('clients.update');
                Route::delete('/clients/{client}', 'deleteClient')->middleware(['feature:clients_projects', 'workspace.writable'])->name('clients.destroy');
                Route::post('/projects', 'storeProject')->middleware(['feature:clients_projects', 'workspace.writable'])->name('projects.store');
                Route::delete('/projects/{project}', 'deleteProject')->middleware(['feature:clients_projects', 'workspace.writable'])->name('projects.destroy');
                Route::post('/rates', 'storeRate')->middleware(['feature:earnings', 'workspace.writable'])->name('rates.store');
                Route::post('/schedules', 'storeExpectedSchedule')->middleware(['feature:recurring_schedules', 'workspace.writable'])->name('schedules.store');
                Route::post('/schedules/{schedule}/convert', 'convertSchedule')->middleware(['feature:recurring_schedules', 'workspace.writable'])->name('schedules.convert');
                Route::put('/reminders', 'updateReminders')->middleware('feature:smart_reminders')->name('reminders.update');
                Route::post('/report-templates', 'storeTemplate')->middleware(['feature:export_templates', 'workspace.writable'])->name('templates.store');
                Route::post('/report-templates/{template}/schedule', 'scheduleReport')->middleware(['feature:scheduled_reports', 'workspace.writable'])->name('templates.schedule');
                Route::post('/invoices', 'createInvoice')->middleware(['feature:invoicing', 'workspace.writable'])->name('invoices.store');
                Route::get('/invoices/{invoice}', 'showInvoice')->middleware('feature:invoicing')->name('invoices.show');
                Route::get('/invoices/{invoice}/pdf', 'invoicePdf')->middleware('feature:invoicing')->name('invoices.pdf');
                Route::post('/invoices/{invoice}/status', 'updateInvoiceStatus')->middleware(['feature:invoicing', 'workspace.writable'])->name('invoices.status');
            });
            Route::prefix('pro')->name('pro.')->controller(ProToolsController::class)->group(function (): void {
                Route::get('/clients', 'clients')->name('clients.index');
                Route::get('/earnings', 'earnings')->name('earnings.index');
                Route::get('/schedules', 'schedules')->name('schedules.index');
                Route::get('/reminders', 'reminders')->name('reminders.index');
                Route::get('/reports', 'reports')->name('reports.index');
                Route::get('/calendars', 'calendars')->name('calendars.index');
                Route::get('/invoices', 'invoices')->name('invoices.index');
            });
            Route::prefix('pro/calendars')->name('pro.calendars.')->controller(CalendarIntegrationController::class)->middleware('feature:calendar_integrations')->group(function (): void {
                Route::get('/{provider}/connect', 'redirect')->name('redirect');
                Route::get('/{provider}/callback', 'callback')->name('callback');
                Route::post('/connections/{connection}/sync', 'sync')->name('sync');
                Route::delete('/connections/{connection}', 'disconnect')->name('disconnect');
                Route::post('/events/{event}/convert', 'convert')->middleware('workspace.writable')->name('events.convert');
                Route::post('/events/{event}/ignore', 'ignore')->name('events.ignore');
            });
            Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
            Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
            Route::put('/settings/hours', [HoursSettingsController::class, 'update'])->middleware('workspace.writable')->name('settings.hours.update');

            Route::prefix('hours')->name('hours.')->controller(HoursController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/events', 'events')->name('events');
                Route::post('/entries', 'store')->middleware('workspace.writable')->name('entries.store');
                Route::patch('/entries/{hoursEntry}', 'update')->middleware('workspace.writable')->name('entries.update');
                Route::delete('/entries/{hoursEntry}', 'destroy')->middleware('workspace.writable')->name('entries.destroy');
                Route::get('/reports', 'report')->name('reports.index');
                Route::get('/reports/export/excel', 'excel')->middleware('feature:excel_pdf_exports')->name('reports.excel');
                Route::get('/reports/export/csv', 'csv')->name('reports.csv');
                Route::get('/reports/print', 'print')->middleware('feature:excel_pdf_exports')->name('reports.print');
            });
        });
    });
});
