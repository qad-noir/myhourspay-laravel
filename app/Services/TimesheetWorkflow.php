<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimesheetWorkflow
{
    public function submit(User $user, Workspace $workspace, array $data): Timesheet
    {
        return DB::transaction(function () use ($user, $workspace, $data) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $week = CarbonImmutable::parse($data['week_start'])->startOfWeek();
            $entries = $user->hoursEntries()->forWorkspace($workspace)->whereBetween('work_date', [$week->toDateString(), $week->endOfWeek()->toDateString()]);
            if (! $entries->exists()) {
                throw ValidationException::withMessages(['week_start' => 'Log at least one hours entry in this week before submitting a timesheet.']);
            }
            $sheet = Timesheet::firstOrCreate(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'week_start' => $week], ['status' => 'draft']);
            abort_if($sheet->isLocked(), 409, 'Approved timesheets must be reopened before changes.');
            $entries->update(['timesheet_id' => $sheet->id]);
            $sheet->update(['status' => 'submitted', 'submission_note' => $data['submission_note'] ?? null, 'submitted_at' => now(), 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'locked_at' => null]);
            $this->record($workspace, $user, $sheet, 'submitted');

            return $sheet->fresh();
        });
    }

    public function review(User $user, Workspace $workspace, Timesheet $sheet, array $data): Timesheet
    {
        return DB::transaction(function () use ($user, $workspace, $sheet, $data) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $sheet->refresh();
            abort_unless($sheet->workspace_id === $workspace->id, 404);
            abort_unless(app(WorkspaceRoles::class)->canReview($user, $workspace), 403);
            abort_if($data['decision'] !== 'reopened' && $sheet->status !== 'submitted', 409, 'Only submitted timesheets can be reviewed.');
            $sheet->update(['status' => $data['decision'] === 'reopened' ? 'draft' : $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'locked_at' => $data['decision'] === 'approved' ? now() : null]);
            $this->record($workspace, $user, $sheet, $data['decision']);

            return $sheet->fresh();
        });
    }

    private function record(Workspace $workspace, User $user, Timesheet $sheet, string $event): void
    {
        app(WorkspaceActivity::class)->record($workspace, $user, 'timesheet.'.$event, $sheet, ['review_note' => $sheet->review_note]);
        app(OutboundWebhookDispatcher::class)->queue($workspace, 'timesheet.'.$event, ['timesheet_id' => $sheet->id, 'user_id' => $sheet->user_id, 'week_start' => $sheet->week_start->toDateString()]);
    }
}
