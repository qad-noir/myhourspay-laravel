<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use App\Models\Workspace;
use App\Services\FeatureAccess;
use App\Services\MobileMutation;
use App\Services\TimesheetWorkflow;
use App\Services\WorkspaceRoles;
use App\Support\MobileResponse;
use Illuminate\Http\Request;

class MobileTimesheetController extends Controller
{
    private function access(Request $request, Workspace $workspace, bool $write = false): void
    {
        app(MobileWorkspaceController::class)->authorizeWorkspace($request, $workspace, $write);
        if (! app(FeatureAccess::class)->allows($request->user(), 'timesheet_approvals', $workspace)) {
            MobileResponse::fail('feature_unavailable', 'Timesheet approvals are unavailable for this workspace.');
        }
    }

    public function index(Request $request, Workspace $workspace)
    {
        $this->access($request, $workspace);
        $data = $request->validate(['scope' => 'sometimes|in:mine,review', 'page' => 'sometimes|integer|min:1']);
        $review = ($data['scope'] ?? 'mine') === 'review';
        abort_if($review && ! app(WorkspaceRoles::class)->canReview($request->user(), $workspace), 403);
        $page = $workspace->timesheets()->when(! $review, fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('user:id,name')->orderByDesc('week_start')->orderByDesc('id')->paginate(50);

        return response()->json(['data' => $page->getCollection()->map(fn ($sheet) => $this->data($sheet)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Request $request, Workspace $workspace, Timesheet $timesheet)
    {
        $this->access($request, $workspace);
        abort_unless($timesheet->workspace_id === $workspace->id && ($timesheet->user_id === $request->user()->id || app(WorkspaceRoles::class)->canReview($request->user(), $workspace)), 404);

        return response()->json(['data' => [...$this->data($timesheet), 'entries' => $timesheet->entries()->orderBy('work_date')->get()->map(fn ($entry) => app(MobileWorkspaceController::class)->entryData($entry))]]);
    }

    public function submit(Request $request, Workspace $workspace, TimesheetWorkflow $workflow)
    {
        $this->access($request, $workspace, true);

        return app(MobileMutation::class)->run($request, $workspace, function () use ($request, $workspace, $workflow) {
            $data = $request->validate(['week_start' => 'required|date_format:Y-m-d', 'submission_note' => 'nullable|string|max:2000']);

            return response()->json(['data' => $this->data($workflow->submit($request->user(), $workspace, $data))], 201);
        });
    }

    public function review(Request $request, Workspace $workspace, Timesheet $timesheet, TimesheetWorkflow $workflow)
    {
        $this->access($request, $workspace, true);
        abort_unless($timesheet->workspace_id === $workspace->id, 404);
        abort_unless(app(WorkspaceRoles::class)->canReview($request->user(), $workspace), 403);

        return app(MobileMutation::class)->run($request, $workspace, function () use ($request, $workspace, $timesheet, $workflow) {
            $data = $request->validate(['decision' => 'required|in:approved,rejected,reopened', 'review_note' => 'nullable|string|max:2000', 'version' => 'required|string|size:64']);
            $timesheet->refresh();
            if (! hash_equals($this->data($timesheet)['version'], $data['version'])) {
                MobileResponse::fail('timesheet_changed', 'Reload this timesheet before reviewing it.', 409);
            }

            return response()->json(['data' => $this->data($workflow->review($request->user(), $workspace, $timesheet, $data))]);
        });
    }

    private function data(Timesheet $sheet): array
    {
        $attributes = $sheet->only(['id', 'workspace_id', 'user_id', 'status', 'submission_note', 'review_note', 'reviewed_by']);
        $attributes['week_start'] = $sheet->week_start->toDateString();
        foreach (['submitted_at', 'reviewed_at', 'locked_at', 'updated_at'] as $field) {
            $attributes[$field] = $sheet->$field?->toISOString();
        }
        $entries = $sheet->entries()->orderBy('id')->get();
        $attributes['version'] = hash('sha256', json_encode([$attributes, $entries->map(fn ($entry) => app(MobileWorkspaceController::class)->entryData($entry))]));

        return [...$attributes, 'user_name' => $sheet->user?->name, 'total_minutes' => (int) $entries->sum('net_minutes')];
    }
}
