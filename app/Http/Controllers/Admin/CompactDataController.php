<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoursEntry;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CompactTable;
use App\Services\HoursCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompactDataController extends Controller
{
    public function userHours(Request $request, User $user): JsonResponse
    {
        return $this->hours($request, HoursEntry::query()->where('user_id', $user->id), false);
    }

    public function workspaceHours(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->hours($request, HoursEntry::query()->where('workspace_id', $workspace->id), true);
    }

    private function hours(Request $request, $query, bool $workspace): JsonResponse
    {
        $query->with(['user', 'workspace'])->leftJoin('users', 'users.id', '=', 'hours_entries.user_id')
            ->leftJoin('workspaces', 'workspaces.id', '=', 'hours_entries.workspace_id')->select('hours_entries.*');
        $person = $workspace ? 'users.name' : 'workspaces.name';

        return CompactTable::query($query, $request, ['hours_entries.work_date', $person, 'hours_entries.start_time', 'hours_entries.break_minutes', null], ['hours_entries.work_date', $person])
            ->addColumn('date', fn ($entry) => $entry->work_date->format('Y-m-d'))
            ->addColumn('person', fn ($entry) => ($workspace ? $entry->user?->name : $entry->workspace?->name) ?? '—')
            ->addColumn('time', fn ($entry) => substr($entry->start_time, 0, 5).'–'.substr($entry->end_time, 0, 5))
            ->addColumn('break', fn ($entry) => $entry->break_minutes.'m '.$entry->break_type)
            ->addColumn('net', fn ($entry) => app(HoursCalculator::class)->enrichEntry($entry)['net_formatted'])
            ->only(['date', 'person', 'time', 'break', 'net'])->toJson();
    }

    public function support(Request $request): JsonResponse
    {
        $request->validate(['status' => 'nullable|in:open,in_progress,closed', 'priority' => 'nullable|in:normal,priority,urgent', 'q' => 'nullable|string|max:200']);
        $query = SupportRequest::query()->with('user')->leftJoin('users', 'users.id', '=', 'support_requests.user_id')->select('support_requests.*');
        foreach (['status', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where('support_requests.'.$filter, $request->input($filter));
            }
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('support_requests.subject', 'like', '%'.$request->input('q').'%')->orWhere('support_requests.public_id', 'like', '%'.$request->input('q').'%')->orWhere('users.email', 'like', '%'.$request->input('q').'%'));
        }

        return CompactTable::query($query, $request, ['support_requests.public_id', 'users.name', 'support_requests.subject', 'support_requests.plan_key', 'support_requests.priority', 'support_requests.status', null], ['support_requests.public_id', 'support_requests.subject', 'users.name', 'users.email'])
            ->addColumn('customer', fn ($item) => ($item->user?->name ?? 'Deleted user').' · '.($item->user?->email ?? ''))
            ->addColumn('actions', fn ($item) => '<a href="'.e(route('admin.support.show', $item)).'" wire:navigate>Review</a>')
            ->rawColumns(['actions'])->only(['public_id', 'customer', 'subject', 'plan_key', 'priority', 'status', 'actions'])->toJson();
    }
}
