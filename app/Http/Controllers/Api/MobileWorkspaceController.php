<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HoursEntry;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FeatureAccess;
use App\Services\HoursCalculator;
use App\Services\MobileMutation;
use App\Services\SubscriptionState;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceRoles;
use App\Support\MobileResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MobileWorkspaceController extends Controller
{
    public function workspaces(Request $request)
    {
        return response()->json(['data' => $request->user()->workspaces()->orderBy('workspaces.id')->get()->map(fn ($workspace) => $this->workspaceData($request, $workspace))]);
    }

    private function workspaceData(Request $request, Workspace $workspace): array
    {
        return [...$workspace->only(['id', 'name', 'currency', 'default_break_minutes', 'default_break_type', 'weekly_target_minutes']),
            'role' => app(WorkspaceRoles::class)->role($request->user(), $workspace),
            'writable' => app(WorkspaceAccess::class)->isWritable($request->user(), $workspace),
            'timezone' => $workspace->timezone ?: config('hours.timezone'),
            'features' => collect(['clients_projects', 'timesheet_approvals'])->mapWithKeys(fn ($key) => [$key => app(FeatureAccess::class)->allows($request->user(), $key, $workspace)])->all()];
    }

    public function createWorkspace(Request $request)
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate(['name' => 'required|string|min:3|max:100', 'position' => 'required|string|min:3|max:100',
            'default_break_type' => 'required|in:paid,unpaid', 'default_break_minutes' => 'required|integer|min:0|max:1439',
            'weekly_target_minutes' => 'required|integer|min:60|max:10080']);

        return DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! app(WorkspaceAccess::class)->canCreateOwnedWorkspace($user)) {
                MobileResponse::fail('workspace_limit_reached', 'Your workspace limit has been reached.');
            }
            if ($user->workspaces()->whereRaw('LOWER(TRIM(workspaces.name)) = ?', [mb_strtolower($data['name'])])->exists()) {
                throw ValidationException::withMessages(['name' => 'You already have a workspace with this name.']);
            }
            $first = ! $user->workspaces()->exists();
            $workspace = $user->ownedWorkspaces()->create(collect($data)->except('position')->all());
            $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => $data['position']]);
            if ($first) {
                $user->hoursEntries()->whereNull('workspace_id')->update(['workspace_id' => $workspace->id]);
            }
            $user->forceFill(['current_workspace_id' => $workspace->id, 'workspace_onboarding_reset_at' => null])->save();

            return response()->json(['data' => $this->workspaceData($request, $workspace->fresh())], 201);
        });
    }

    public function authorizeWorkspace(Request $request, Workspace $workspace, bool $write = false): void
    {
        abort_unless($workspace->users()->whereKey($request->user()->id)->exists(), 404);
        if (app(SubscriptionState::class)->needsTrialChoice($request->user())) {
            MobileResponse::fail('trial_choice_required', 'Your account requires a trial choice.');
        }
        if ($write && ! app(WorkspaceAccess::class)->isWritable($request->user(), $workspace)) {
            MobileResponse::fail('workspace_read_only', 'This workspace is read-only for your account.');
        }
    }

    private function feature(Request $request, Workspace $workspace, string $feature): void
    {
        if (! app(FeatureAccess::class)->allows($request->user(), $feature, $workspace)) {
            MobileResponse::fail('feature_unavailable', 'This feature is unavailable for this workspace.');
        }
    }

    public function hours(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $data = $request->validate(['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        if (CarbonImmutable::parse($data['start'])->diffInDays(CarbonImmutable::parse($data['end'])) > 366) {
            throw ValidationException::withMessages(['end' => 'Choose a range of at most 366 days.']);
        }
        $query = $request->user()->hoursEntries()->forWorkspace($workspace)->forPeriod($data['start'], $data['end'])->orderBy('work_date')->orderBy('id');
        $summary = app(HoursCalculator::class)->forWorkspace($workspace)->summarizeEntries((clone $query)->get(), $data['start'], $data['end'], false);
        $page = $query->paginate($data['per_page'] ?? 50);

        return response()->json(['data' => $page->getCollection()->map(fn ($entry) => $this->entryData($entry)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'summary' => collect($summary)->only(['total_minutes', 'total_formatted', 'overtime_minutes', 'overtime_formatted', 'weeks'])->all()]);
    }

    public function entryData(HoursEntry $entry): array
    {
        return [...$entry->only(['id', 'workspace_id', 'project_id', 'timesheet_id', 'break_minutes', 'break_type', 'notes', 'billable', 'net_minutes']),
            'work_date' => $entry->work_date->toDateString(), 'start_time' => substr($entry->start_time, 0, 5), 'end_time' => substr($entry->end_time, 0, 5),
            'version' => hash('sha256', json_encode($entry->getAttributes()))];
    }

    public function saveHours(Request $request, Workspace $workspace, ?HoursEntry $entry = null)
    {
        $this->authorizeWorkspace($request, $workspace, true);
        if ($entry?->exists) {
            abort_unless($entry->workspace_id === $workspace->id && $entry->user_id === $request->user()->id, 404);
        }

        return app(MobileMutation::class)->run($request, $workspace, function () use ($request, $workspace, $entry) {
            if ($entry?->exists) {
                $entry->refresh();
                $request->validate(['version' => 'required|string|size:64']);
                if (! hash_equals($this->entryData($entry)['version'], $request->input('version'))) {
                    MobileResponse::fail('entry_changed', 'This entry has changed. Reload it before saving.', 409);
                }
                $this->unlocked($request, $workspace, $entry->work_date->toDateString());
                if ($entry->timesheet?->isLocked()) {
                    MobileResponse::fail('timesheet_locked', 'A manager must reopen this timesheet.', 409);
                }
            }
            $data = $request->validate([
                'work_date' => ['required', 'date_format:Y-m-d', Rule::unique('hours_entries')->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->ignore($entry?->id)],
                'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
                'break_minutes' => 'required|integer|min:0|max:'.config('hours.maximum_break_minutes'), 'break_type' => 'required|in:paid,unpaid',
                'notes' => 'nullable|string|max:'.config('hours.maximum_notes_length'), 'billable' => 'sometimes|boolean',
                'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('workspace_id', $workspace->id)->where('active', true)],
            ], ['work_date.unique' => 'This date already has an entry. Try editing it instead.']);
            if (! empty($data['project_id']) || ! empty($data['billable'])) {
                $this->feature($request, $workspace, 'clients_projects');
            }
            try {
                app(HoursCalculator::class)->validateDate($data['work_date']);
                app(HoursCalculator::class)->calculateNetMinutes($data['start_time'], $data['end_time'], $data['break_minutes'], $data['break_type']);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['end_time' => $exception->getMessage()]);
            }
            $this->unlocked($request, $workspace, $data['work_date']);
            $created = ! $entry?->exists;
            $record = $created ? new HoursEntry(['user_id' => $request->user()->id, 'workspace_id' => $workspace->id]) : $entry;
            if (! $created && $entry->work_date->toDateString() !== $data['work_date']) {
                $record->timesheet_id = null;
            }
            $record->fill($data)->save();

            return response()->json(['data' => $this->entryData($record->fresh())], $created ? 201 : 200);
        });
    }

    private function unlocked(Request $request, Workspace $workspace, string $date): void
    {
        $locked = Timesheet::where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)
            ->whereDate('week_start', CarbonImmutable::parse($date)->startOfWeek()->toDateString())->whereIn('status', ['approved', 'locked'])->exists();
        if ($locked) {
            MobileResponse::fail('timesheet_locked', 'A manager must reopen this timesheet.', 409);
        }
    }

    public function projects(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->feature($request, $workspace, 'clients_projects');
        $request->validate(['page' => 'sometimes|integer|min:1']);
        $page = $workspace->projects()->where('active', true)->orderBy('name')->paginate(100, ['id', 'name', 'client_id']);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }
}
