<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\WorkspaceRoles;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessToolsController extends Controller
{
    private const FEATURES = [
        'team_members',
        'roles_permissions',
        'timesheet_approvals',
        'leave_tracking',
        'payroll_exports',
        'workspace_audit',
        'custom_branding',
        'outbound_webhooks',
        'priority_support',
    ];

    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly WorkspaceRoles $roles,
        private readonly FeatureAccess $features,
    ) {}

    public function overview(Request $request): View
    {
        $context = $this->context($request);
        $workspace = $context['workspace'];
        $access = $context['access'];

        return view('business.overview', $context + [
            'moduleCounts' => [
                'team' => $access['team_members'] ? $workspace->users()->count().' members' : 'Premium feature',
                'timesheets' => $access['timesheet_approvals'] ? $workspace->timesheets()->where('status', 'submitted')->count().' awaiting review' : 'Premium feature',
                'leave' => $access['leave_tracking'] ? $workspace->leaveRequests()->where('status', 'pending')->count().' pending' : 'Premium feature',
                'payroll' => $access['payroll_exports'] ? $workspace->payrollProfiles()->count().' profiles' : 'Premium feature',
                'branding' => $access['custom_branding'] && $workspace->branding()->exists() ? 'Configured' : 'Not configured',
                'activity' => $access['workspace_audit'] ? $workspace->activityLogs()->count().' records' : 'Premium feature',
                'webhooks' => $access['outbound_webhooks'] ? $workspace->webhookEndpoints()->count().' endpoints' : 'Premium feature',
                'support' => $access['priority_support'] ? $request->user()->supportRequests()->where('workspace_id', $workspace->id)->whereIn('status', ['open', 'in_progress'])->count().' open' : 'Premium feature',
            ],
            'workflowReady' => [
                'members' => $workspace->users()->count() > 1,
                'hours' => $workspace->hoursEntries()->exists(),
                'submitted' => $workspace->timesheets()->where('status', 'submitted')->exists(),
                'approved' => $workspace->timesheets()->whereIn('status', ['approved', 'locked'])->exists(),
            ],
        ]);
    }

    public function team(Request $request): View
    {
        $context = $this->context($request);

        return view('business.team', $context + [
            'members' => $context['access']['team_members'] ? $context['workspace']->users()->orderBy('name')->get() : collect(),
            'invitations' => $context['access']['team_members'] ? $context['workspace']->invitations()->where('status', 'pending')->latest()->get() : collect(),
        ]);
    }

    public function timesheets(Request $request): View
    {
        $context = $this->context($request);
        $data = $request->validate(['week' => ['nullable', 'date']]);
        $week = CarbonImmutable::parse($data['week'] ?? now())->startOfWeek();
        $entryQuery = $request->user()->hoursEntries()->forWorkspace($context['workspace'])->whereBetween('work_date', [$week->toDateString(), $week->copy()->endOfWeek()->toDateString()]);

        return view('business.timesheets', $context + [
            'timesheets' => $context['access']['timesheet_approvals'] ? $context['workspace']->timesheets()->with(['user', 'reviewer'])->latest('week_start')->limit(30)->get() : collect(),
            'selectedWeek' => $week,
            'currentWeekEntryCount' => $entryQuery->count(),
            'currentWeekMinutes' => (int) $entryQuery->sum('net_minutes'),
        ]);
    }

    public function leave(Request $request): View
    {
        $context = $this->context($request);

        return view('business.leave', $context + [
            'leaveTypes' => $context['access']['leave_tracking'] ? $context['workspace']->leaveTypes()->where('active', true)->orderBy('name')->get() : collect(),
            'leaveRequests' => $context['access']['leave_tracking'] ? $context['workspace']->leaveRequests()->with(['user', 'type'])->latest()->limit(30)->get() : collect(),
        ]);
    }

    public function payroll(Request $request): View
    {
        $context = $this->context($request);

        $latestApprovedWeek = $context['access']['payroll_exports'] ? $context['workspace']->timesheets()->whereIn('status', ['approved', 'locked'])->max('week_start') : null;

        return view('business.payroll', $context + [
            'exportStart' => $latestApprovedWeek ? CarbonImmutable::parse($latestApprovedWeek)->toDateString() : null,
            'exportEnd' => $latestApprovedWeek ? CarbonImmutable::parse($latestApprovedWeek)->endOfWeek()->toDateString() : null,
            'payrollProfiles' => $context['access']['payroll_exports'] ? $context['workspace']->payrollProfiles()->orderBy('name')->get() : collect(),
            'approvedTimesheetCount' => $context['access']['payroll_exports'] ? $context['workspace']->timesheets()->whereIn('status', ['approved', 'locked'])->count() : 0,
        ]);
    }

    public function branding(Request $request): View
    {
        $context = $this->context($request);

        return view('business.branding', $context + [
            'branding' => $context['access']['custom_branding'] ? $context['workspace']->branding()->firstOrNew() : null,
        ]);
    }

    public function activity(Request $request): View
    {
        $context = $this->context($request);

        return view('business.activity', $context + [
            'activityLogs' => $context['access']['workspace_audit'] ? $context['workspace']->activityLogs()->with('actor')->latest('occurred_at')->limit(50)->get() : collect(),
        ]);
    }

    public function webhooks(Request $request): View
    {
        $context = $this->context($request);

        return view('business.webhooks', $context + [
            'webhooks' => $context['access']['outbound_webhooks'] ? $context['workspace']->webhookEndpoints()->withCount('deliveries')->get() : collect(),
        ]);
    }

    public function support(Request $request): View
    {
        $context = $this->context($request);

        return view('business.support', $context + [
            'supportRequests' => $context['access']['priority_support']
                ? SupportRequest::query()->where('workspace_id', $context['workspace']->id)->where('user_id', $request->user()->id)->latest()->limit(20)->get()
                : collect(),
        ]);
    }

    private function context(Request $request): array
    {
        $workspace = $this->current->for($request->user());
        $access = collect(self::FEATURES)
            ->mapWithKeys(fn (string $feature) => [$feature => $this->features->allows($request->user(), $feature, $workspace)]);
        $role = $this->roles->role($request->user(), $workspace);
        $canManage = $this->roles->canManage($request->user(), $workspace);
        $canReview = $this->roles->canReview($request->user(), $workspace);
        $canPayroll = $this->roles->canRunPayroll($request->user(), $workspace);

        return compact('workspace', 'access', 'role', 'canManage', 'canReview', 'canPayroll');
    }
}
