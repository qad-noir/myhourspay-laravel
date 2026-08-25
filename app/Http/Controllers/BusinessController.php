<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\OutboundWebhookEndpoint;
use App\Models\PayrollExportProfile;
use App\Models\SupportRequest;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\BusinessSeatBilling;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\OperationalIncidentRecorder;
use App\Services\OutboundWebhookDispatcher;
use App\Services\PublicWebhookUrl;
use App\Services\WorkspaceActivity;
use App\Services\WorkspaceRoles;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BusinessController extends Controller
{
    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly WorkspaceRoles $roles,
        private readonly WorkspaceActivity $activity,
        private readonly FeatureAccess $features,
    ) {}

    public function index(Request $request): View
    {
        $workspace = $this->current->for($request->user());
        $access = collect(['team_members', 'roles_permissions', 'timesheet_approvals', 'leave_tracking', 'payroll_exports', 'workspace_audit', 'custom_branding', 'outbound_webhooks', 'priority_support'])->mapWithKeys(fn (string $feature) => [$feature => $this->features->allows($request->user(), $feature, $workspace)]);

        return view('business.index', [
            'workspace' => $workspace,
            'role' => $this->roles->role($request->user(), $workspace),
            'access' => $access,
            'members' => $access['team_members'] ? $workspace->users()->orderBy('name')->get() : collect(),
            'invitations' => $access['team_members'] ? $workspace->invitations()->where('status', 'pending')->latest()->get() : collect(),
            'timesheets' => $access['timesheet_approvals'] ? $workspace->timesheets()->with(['user', 'reviewer'])->latest('week_start')->limit(30)->get() : collect(),
            'leaveTypes' => $access['leave_tracking'] ? $workspace->leaveTypes()->where('active', true)->orderBy('name')->get() : collect(),
            'leaveRequests' => $access['leave_tracking'] ? $workspace->leaveRequests()->with(['user', 'type'])->latest()->limit(30)->get() : collect(),
            'payrollProfiles' => $access['payroll_exports'] ? $workspace->payrollProfiles()->get() : collect(),
            'branding' => $access['custom_branding'] ? $workspace->branding()->firstOrNew() : null,
            'activityLogs' => $access['workspace_audit'] ? $workspace->activityLogs()->with('actor')->latest('occurred_at')->limit(50)->get() : collect(),
            'webhooks' => $access['outbound_webhooks'] ? $workspace->webhookEndpoints()->withCount('deliveries')->get() : collect(),
            'supportRequests' => $access['priority_support'] ? $request->user()->supportRequests()->latest()->limit(10)->get() : collect(),
            'canManage' => $this->roles->canManage($request->user(), $workspace),
            'canReview' => $this->roles->canReview($request->user(), $workspace),
            'canPayroll' => $this->roles->canRunPayroll($request->user(), $workspace),
        ]);
    }

    public function invite(Request $request, BusinessSeatBilling $seats, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'role' => ['required', Rule::in(['administrator', 'manager', 'payroll', 'member'])], 'position' => ['nullable', 'string', 'max:100']]);
        if ($workspace->users()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'That user is already a workspace member.']);
        }
        $token = Str::random(64);
        $invitation = $workspace->invitations()->updateOrCreate(['email' => Str::lower($data['email']), 'status' => 'pending'], ['public_id' => (string) Str::uuid(), 'invited_by' => $request->user()->id, 'role' => $data['role'], 'position' => $data['position'] ?? null, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
        try {
            Notification::route('mail', $invitation->email)->notify(new WorkspaceInvitationNotification($invitation->load(['workspace', 'inviter']), $token));
        } catch (Throwable $exception) {
            Log::error('Workspace invitation delivery failed.', ['invitation_id' => $invitation->id, 'workspace_id' => $workspace->id, 'exception' => $exception]);
            $incident = $incidents->record('workspace.invitation_delivery_failed', $exception, ['name' => $request->user()->name, 'email' => $request->user()->email, 'exception_message' => "Invitation {$invitation->id} was saved but its email could not be delivered."]);

            return back()->withErrors(['invitation' => "The invitation was saved but email delivery failed. Reference: {$incident->reference}."]);
        }
        $this->activity->record($workspace, $request->user(), 'workspace.invitation_sent', $invitation, ['email' => $invitation->email, 'role' => $invitation->role]);

        return back()->with('status', 'Workspace invitation sent.');
    }

    public function acceptInvitation(Request $request, WorkspaceInvitation $invitation, BusinessSeatBilling $seats): RedirectResponse
    {
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', (string) $request->query('token'))), 403);
        abort_if($invitation->status !== 'pending' || $invitation->expires_at->isPast(), 410, 'This invitation has expired.');
        abort_unless(Str::lower($request->user()->email) === Str::lower($invitation->email), 403, 'Sign in with the invited email address.');
        $workspace = $invitation->workspace()->with('owner')->firstOrFail();
        try {
            $seats->sync($workspace->owner, $seats->activeSeats($workspace->owner) + 1);
            DB::transaction(function () use ($request, $workspace, $invitation): void {
                $workspace->users()->syncWithoutDetaching([$request->user()->id => ['role' => $invitation->role, 'position' => $invitation->position]]);
                $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            });
        } catch (Throwable) {
            return redirect()->route('billing.index')->withErrors(['seats' => 'The workspace could not add a billed seat. The invitation remains pending and no access was granted.']);
        }
        $this->activity->record($workspace, $request->user(), 'workspace.invitation_accepted', $invitation, ['role' => $invitation->role]);
        $request->user()->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard')->with('status', 'You joined '.$workspace->name.'.');
    }

    public function updateMember(Request $request, User $member): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        abort_if((int) $workspace->owner_id === (int) $member->id, 422, 'The owner role cannot be changed.');
        abort_unless($workspace->users()->whereKey($member->id)->exists(), 404);
        $data = $request->validate(['role' => ['required', Rule::in(['administrator', 'manager', 'payroll', 'member'])], 'position' => ['nullable', 'string', 'max:100']]);
        $workspace->users()->updateExistingPivot($member->id, $data);
        $this->activity->record($workspace, $request->user(), 'workspace.member_updated', $member, $data);

        return back()->with('status', 'Member access updated.');
    }

    public function removeMember(Request $request, User $member, BusinessSeatBilling $seats): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        abort_if((int) $workspace->owner_id === (int) $member->id, 422, 'The workspace owner cannot be removed.');
        abort_unless($workspace->users()->whereKey($member->id)->exists(), 404);
        $workspace->users()->detach($member->id);
        if ((int) $member->current_workspace_id === (int) $workspace->id) {
            $member->update(['current_workspace_id' => $member->workspaces()->value('workspaces.id')]);
        }
        try {
            $seats->sync($workspace->owner);
        } catch (Throwable) { /* Reconciliation will retry and an incident already exists. */
        }
        $this->activity->record($workspace, $request->user(), 'workspace.member_removed', $member);

        return back()->with('status', 'Member removed. Their recorded hours were retained.');
    }

    public function submitTimesheet(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request);
        $data = $request->validate(['week_start' => ['required', 'date'], 'submission_note' => ['nullable', 'string', 'max:2000']]);
        $week = CarbonImmutable::parse($data['week_start'])->startOfWeek();
        $timesheet = Timesheet::query()->firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'week_start' => $week], ['status' => 'draft']);
        abort_if($timesheet->isLocked(), 422, 'Approved timesheets must be reopened before changes.');
        DB::transaction(function () use ($timesheet, $request, $workspace, $week, $data): void {
            $request->user()->hoursEntries()->forWorkspace($workspace)->whereBetween('work_date', [$week->toDateString(), $week->endOfWeek()->toDateString()])->update(['timesheet_id' => $timesheet->id]);
            $timesheet->update(['status' => 'submitted', 'submission_note' => $data['submission_note'] ?? null, 'submitted_at' => now(), 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
        });
        $this->activity->record($workspace, $request->user(), 'timesheet.submitted', $timesheet);
        app(OutboundWebhookDispatcher::class)->queue($workspace, 'timesheet.submitted', ['timesheet_id' => $timesheet->id, 'user_id' => $timesheet->user_id, 'week_start' => $timesheet->week_start->toDateString()]);

        return back()->with('status', 'Weekly timesheet submitted for approval.');
    }

    public function reviewTimesheet(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'review');
        abort_unless($timesheet->workspace_id === $workspace->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'reopened'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        abort_if($data['decision'] !== 'reopened' && $timesheet->status !== 'submitted', 422, 'Only submitted timesheets can be reviewed.');
        $timesheet->update(['status' => $data['decision'] === 'reopened' ? 'draft' : $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'locked_at' => $data['decision'] === 'approved' ? now() : null]);
        $this->activity->record($workspace, $request->user(), 'timesheet.'.$data['decision'], $timesheet, ['review_note' => $data['review_note'] ?? null]);
        app(OutboundWebhookDispatcher::class)->queue($workspace, 'timesheet.'.$data['decision'], ['timesheet_id' => $timesheet->id, 'user_id' => $timesheet->user_id, 'week_start' => $timesheet->week_start->toDateString()]);

        return back()->with('status', 'Timesheet '.$data['decision'].'.');
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('leave_types')->where('workspace_id', $workspace->id)], 'colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'paid' => ['nullable', 'boolean']]);
        $type = $workspace->leaveTypes()->create([...$data, 'paid' => $request->boolean('paid')]);
        $this->activity->record($workspace, $request->user(), 'leave_type.created', $type);

        return back()->with('status', 'Leave type created.');
    }

    public function requestLeave(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request);
        $data = $request->validate(['leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('workspace_id', $workspace->id)], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'minutes_per_day' => ['nullable', 'integer', 'min:1', 'max:1440'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $leave = $workspace->leaveRequests()->create([...$data, 'user_id' => $request->user()->id]);
        $this->activity->record($workspace, $request->user(), 'leave.requested', $leave);

        return back()->with('status', 'Leave request submitted. Leave remains separate from worked hours.');
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'review');
        abort_unless($leaveRequest->workspace_id === $workspace->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $leaveRequest->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);
        $this->activity->record($workspace, $request->user(), 'leave.'.$data['decision'], $leaveRequest);

        return back()->with('status', 'Leave request '.$data['decision'].'.');
    }

    public function storePayrollProfile(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'payroll');
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('payroll_export_profiles')->where('workspace_id', $workspace->id)], 'format' => ['required', Rule::in(['csv', 'xlsx'])], 'columns' => ['required', 'array', 'min:1'], 'columns.*' => [Rule::in(['employee', 'email', 'week', 'regular_minutes', 'overtime_minutes', 'paid_break_minutes', 'unpaid_break_minutes', 'earnings_minor', 'currency'])]]);
        $profile = $workspace->payrollProfiles()->create($data);
        $this->activity->record($workspace, $request->user(), 'payroll_profile.created', $profile);

        return back()->with('status', 'Payroll export profile saved.');
    }

    public function payroll(Request $request, PayrollExportProfile $profile): BinaryFileResponse|StreamedResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'payroll');
        abort_unless($profile->workspace_id === $workspace->id, 404);
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start']]);
        $rows = $this->payrollRows($workspace->id, $data['start'], $data['end'], $workspace->weekly_target_minutes);
        $this->activity->record($workspace, $request->user(), 'payroll.exported', $profile, ['start' => $data['start'], 'end' => $data['end'], 'format' => $profile->format]);
        if ($profile->format === 'csv') {
            return response()->streamDownload(function () use ($profile, $rows): void {
                $out = fopen('php://output', 'wb');
                fputcsv($out, $profile->columns);
                foreach ($rows as $row) {
                    fputcsv($out, collect($profile->columns)->map(fn ($column) => $row[$column] ?? '')->all());
                } fclose($out);
            }, 'payroll-'.$data['start'].'-'.$data['end'].'.csv', ['Content-Type' => 'text/csv']);
        }
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$profile->columns], null, 'A1');
        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([collect($profile->columns)->map(fn ($column) => $row[$column] ?? '')->all()], null, 'A'.$line++);
        } $path = storage_path('app/private/payroll-'.Str::uuid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'payroll-'.$data['start'].'-'.$data['end'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        $data = $request->validate(['primary_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'email_footer' => ['nullable', 'string', 'max:500'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('workspace-branding', 'public');
        } unset($data['logo']);
        $branding = $workspace->branding()->updateOrCreate([], $data);
        $this->activity->record($workspace, $request->user(), 'workspace.branding_updated', $branding);

        return back()->with('status', 'Workspace branding updated.');
    }

    public function support(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['subject' => ['required', 'string', 'min:3', 'max:190'], 'message' => ['required', 'string', 'min:10', 'max:10000']]);
        $plan = $this->features->effectivePlan($request->user(), $workspace);
        $ticket = SupportRequest::query()->create([...$data, 'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'plan_key' => $plan->key, 'priority' => $plan->key === 'business' ? 'priority' : 'normal']);
        $this->activity->record($workspace, $request->user(), 'support.created', $ticket);

        return back()->with('status', 'Support request '.$ticket->public_id.' created.');
    }

    public function storeWebhook(Request $request, PublicWebhookUrl $validator): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'url' => ['required', 'url:https', 'max:500'], 'secret' => ['required', 'string', 'min:24', 'max:500'], 'events' => ['required', 'array', 'min:1'], 'events.*' => [Rule::in(['timesheet.submitted', 'timesheet.approved', 'timesheet.rejected', 'timesheet.reopened'])]]);
        try {
            $validator->validate($data['url']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['url' => $exception->getMessage()]);
        } $endpoint = $workspace->webhookEndpoints()->create([...$data, 'public_id' => (string) Str::uuid()]);
        $this->activity->record($workspace, $request->user(), 'webhook.created', $endpoint, ['name' => $endpoint->name, 'url' => $endpoint->url]);

        return back()->with('status', 'Signed webhook endpoint created.');
    }

    public function deleteWebhook(Request $request, OutboundWebhookEndpoint $endpoint): RedirectResponse
    {
        $workspace = $this->workspaceAndAuthorize($request, 'manage');
        abort_unless($endpoint->workspace_id === $workspace->id, 404);
        $this->activity->record($workspace, $request->user(), 'webhook.deleted', $endpoint, ['name' => $endpoint->name]);
        $endpoint->delete();

        return back()->with('status', 'Webhook endpoint removed.');
    }

    private function workspaceAndAuthorize(Request $request, ?string $ability = null)
    {
        $workspace = $this->current->for($request->user());
        $allowed = match ($ability) {
            'manage' => $this->roles->canManage($request->user(), $workspace), 'review' => $this->roles->canReview($request->user(), $workspace), 'payroll' => $this->roles->canRunPayroll($request->user(), $workspace), default => $this->roles->role($request->user(), $workspace) !== null
        };
        abort_unless($allowed, 403);

        return $workspace;
    }

    private function payrollRows(int $workspaceId, string $start, string $end, int $target): array
    {
        return Timesheet::query()->with(['user', 'entries'])->where('workspace_id', $workspaceId)->whereIn('status', ['approved', 'locked'])->whereBetween('week_start', [$start, $end])->get()->map(function (Timesheet $sheet) use ($target): array {
            $minutes = $sheet->entries->sum('net_minutes');

            return ['employee' => $sheet->user->name, 'email' => $sheet->user->email, 'week' => $sheet->week_start->toDateString(), 'regular_minutes' => min($minutes, $target), 'overtime_minutes' => max(0, $minutes - $target), 'paid_break_minutes' => $sheet->entries->where('break_type', 'paid')->sum('break_minutes'), 'unpaid_break_minutes' => $sheet->entries->where('break_type', 'unpaid')->sum('break_minutes'), 'earnings_minor' => $sheet->entries->sum('earnings_minor'), 'currency' => $sheet->entries->pluck('currency')->filter()->first() ?? 'GBP'];
        })->all();
    }
}
