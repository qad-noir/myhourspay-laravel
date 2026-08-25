<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HoursEntry;
use App\Models\Timesheet;
use App\Models\Workspace;
use App\Services\HoursCalculator;
use App\Services\WorkspaceRoles;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceApiController extends Controller
{
    public function workspaces(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->workspaces()->select(['workspaces.id', 'workspaces.name', 'workspaces.currency', 'workspaces.weekly_target_minutes'])->get()]);
    }

    public function hours(Request $request, Workspace $workspace): JsonResponse
    {
        $this->member($request, $workspace);
        $data = $request->validate(['start' => ['nullable', 'date'], 'end' => ['nullable', 'date', 'after_or_equal:start'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        $query = HoursEntry::query()->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->with('project:id,name,client_id')->orderByDesc('work_date');
        if (isset($data['start'])) {
            $query->whereDate('work_date', '>=', $data['start']);
        }
        if (isset($data['end'])) {
            $query->whereDate('work_date', '<=', $data['end']);
        }

        return response()->json($query->paginate($data['per_page'] ?? 50));
    }

    public function storeHours(Request $request, Workspace $workspace): JsonResponse
    {
        $this->member($request, $workspace);
        abort_unless($request->user()->tokenCan('hours:write'), 403, 'Token cannot write hours.');
        $data = $request->validate(['work_date' => ['required', 'date_format:Y-m-d', Rule::unique('hours_entries')->where(fn ($query) => $query->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id))], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:1439'], 'break_type' => ['required', Rule::in(['paid', 'unpaid'])], 'project_id' => ['nullable', Rule::exists('projects', 'id')->where('workspace_id', $workspace->id)], 'billable' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:5000']]);
        app(HoursCalculator::class)->calculateNetMinutes($data['start_time'], $data['end_time'], $data['break_minutes'], $data['break_type']);
        $week = CarbonImmutable::parse($data['work_date'])->startOfWeek();
        abort_if(Timesheet::query()->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->whereDate('week_start', $week)->whereIn('status', ['approved', 'locked'])->exists(), 409, 'The timesheet week is locked.');
        $entry = $request->user()->hoursEntries()->create([...$data, 'workspace_id' => $workspace->id]);

        return response()->json(['data' => $entry], 201);
    }

    public function projects(Request $request, Workspace $workspace): JsonResponse
    {
        $this->member($request, $workspace);

        return response()->json(['data' => $workspace->projects()->where('active', true)->with('client:id,name')->paginate(100)]);
    }

    public function invoices(Request $request, Workspace $workspace): JsonResponse
    {
        $this->member($request, $workspace);

        return response()->json(['data' => $workspace->invoices()->with('client:id,name')->latest()->paginate(50)]);
    }

    public function report(Request $request, Workspace $workspace, HoursCalculator $calculator): JsonResponse
    {
        $this->member($request, $workspace);
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start']]);
        $entries = $request->user()->hoursEntries()->forWorkspace($workspace)->forPeriod($data['start'], $data['end'])->get();

        return response()->json(['data' => $calculator->forWorkspace($workspace)->summarizeEntries($entries, $data['start'], $data['end'])]);
    }

    public function approve(Request $request, Workspace $workspace, Timesheet $timesheet, WorkspaceRoles $roles): JsonResponse
    {
        $this->member($request, $workspace);
        abort_unless($request->user()->tokenCan('approvals:write') && $roles->canReview($request->user(), $workspace), 403);
        abort_unless($timesheet->workspace_id === $workspace->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected', 'reopened'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $timesheet->update(['status' => $data['decision'] === 'reopened' ? 'draft' : $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'locked_at' => $data['decision'] === 'approved' ? now() : null]);

        return response()->json(['data' => $timesheet->fresh()]);
    }

    private function member(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->users()->whereKey($request->user()->id)->exists(), 404);
    }
}
