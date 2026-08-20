<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\HoursEntry;
use App\Models\OperationalIncident;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AdminDataController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = ($request->boolean('trash') ? User::onlyTrashed() : User::query())
            ->withCount(['workspaces', 'hoursEntries']);

        return DataTables::eloquent($query)
            ->editColumn('name', fn (User $user) => '<div class="admin-person"><span class="admin-person__avatar">'.e(str($user->name)->substr(0, 1)->upper()).'</span><span><strong>'.e($user->name).'</strong><small>'.e($user->email).'</small></span></div>')
            ->addColumn('status', function (User $user): string {
                $status = $user->deleted_at ? 'Trashed' : ($user->suspended_at ? 'Suspended' : ($user->email_verified_at ? 'Verified' : 'Unverified'));
                return '<span class="admin-status admin-status--'.strtolower($status).'"><i></i>'.e($status).'</span>';
            })
            ->addColumn('workspaces', fn (User $user) => $user->workspaces_count)
            ->addColumn('entries', fn (User $user) => $user->hours_entries_count)
            ->addColumn('joined', fn (User $user) => $user->created_at->format('d M Y'))
            ->addColumn('actions', fn (User $user) => view('admin.partials.user-actions', compact('user'))->render())
            ->filterColumn('name', fn ($query, string $keyword) => $query->where(fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->filterColumn('status', function ($query, string $keyword): void {
                $keyword = strtolower($keyword);
                $query->where(function ($query) use ($keyword): void {
                    if (str_contains($keyword, 'suspend')) $query->whereNotNull('suspended_at');
                    elseif (str_contains($keyword, 'unverified')) $query->whereNull('email_verified_at');
                    elseif (str_contains($keyword, 'verified')) $query->whereNotNull('email_verified_at');
                    elseif (str_contains($keyword, 'trash')) $query->whereNotNull('deleted_at');
                });
            })
            ->orderColumn('status', 'email_verified_at $1')
            ->orderColumn('workspaces', 'workspaces_count $1')
            ->orderColumn('entries', 'hours_entries_count $1')
            ->orderColumn('joined', 'created_at $1')
            ->rawColumns(['name', 'status', 'actions'])
            ->toJson();
    }

    public function workspaces(Request $request): JsonResponse
    {
        $query = ($request->boolean('trash') ? Workspace::onlyTrashed() : Workspace::query())
            ->with('owner')->withCount(['users', 'hoursEntries']);

        return DataTables::eloquent($query)
            ->editColumn('name', fn (Workspace $workspace) => '<div class="admin-person"><span class="admin-person__avatar admin-person__avatar--workspace">'.e(str($workspace->name)->substr(0, 1)->upper()).'</span><span><strong>'.e($workspace->name).'</strong><small>'.e($workspace->default_break_minutes.'m '.$workspace->default_break_type).' break</small></span></div>')
            ->addColumn('owner', fn (Workspace $workspace) => e($workspace->owner?->name ?? 'Deleted user'))
            ->addColumn('members', fn (Workspace $workspace) => $workspace->users_count)
            ->addColumn('entries', fn (Workspace $workspace) => $workspace->hours_entries_count)
            ->addColumn('target', fn (Workspace $workspace) => number_format($workspace->weekly_target_minutes / 60, 1).'h')
            ->addColumn('actions', fn (Workspace $workspace) => view('admin.partials.workspace-actions', compact('workspace'))->render())
            ->filterColumn('owner', fn ($query, string $keyword) => $query->whereHas('owner', fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->orderColumn('owner', fn ($query, string $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'workspaces.owner_id'), $direction))
            ->orderColumn('members', 'users_count $1')
            ->orderColumn('entries', 'hours_entries_count $1')
            ->orderColumn('target', 'weekly_target_minutes $1')
            ->rawColumns(['name', 'actions'])
            ->toJson();
    }

    public function hours(Request $request): JsonResponse
    {
        $query = ($request->boolean('trash') ? HoursEntry::onlyTrashed() : HoursEntry::query())
            ->with(['user', 'workspace']);

        return DataTables::eloquent($query)
            ->addColumn('date', fn (HoursEntry $entry) => $entry->work_date->format('d M Y'))
            ->addColumn('user', fn (HoursEntry $entry) => '<div class="admin-person admin-person--compact"><span class="admin-person__avatar">'.e(str($entry->user?->name ?? '?')->substr(0, 1)->upper()).'</span><span><strong>'.e($entry->user?->name ?? 'Deleted user').'</strong></span></div>')
            ->addColumn('workspace', fn (HoursEntry $entry) => e($entry->workspace?->name ?? 'Deleted workspace'))
            ->addColumn('time', fn (HoursEntry $entry) => substr($entry->start_time, 0, 5).'–'.substr($entry->end_time, 0, 5))
            ->addColumn('break', fn (HoursEntry $entry) => e($entry->break_minutes.'m '.$entry->break_type))
            ->addColumn('actions', fn (HoursEntry $entry) => view('admin.partials.hours-actions', compact('entry'))->render())
            ->filterColumn('user', fn ($query, string $keyword) => $query->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->filterColumn('workspace', fn ($query, string $keyword) => $query->whereHas('workspace', fn ($query) => $query->where('name', 'like', "%{$keyword}%")))
            ->orderColumn('date', 'work_date $1')
            ->orderColumn('user', fn ($query, string $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'hours_entries.user_id'), $direction))
            ->orderColumn('workspace', fn ($query, string $direction) => $query->orderBy(Workspace::select('name')->whereColumn('workspaces.id', 'hours_entries.workspace_id'), $direction))
            ->orderColumn('time', 'start_time $1')
            ->orderColumn('break', 'break_minutes $1')
            ->rawColumns(['user', 'actions'])
            ->toJson();
    }

    public function audits(Request $request): JsonResponse
    {
        $query = AdminAuditLog::query()->with('admin')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')));

        return DataTables::eloquent($query)
            ->editColumn('action', fn (AdminAuditLog $log) => e(str($log->action)->replace('.', ' ')->headline()))
            ->addColumn('admin', fn (AdminAuditLog $log) => e($log->admin?->name ?? 'Deleted admin'))
            ->addColumn('target', fn (AdminAuditLog $log) => e(($log->target_type ? class_basename($log->target_type) : 'Record').' #'.$log->target_id))
            ->addColumn('ip', fn (AdminAuditLog $log) => e($log->ip_address ?? '—'))
            ->addColumn('date', fn (AdminAuditLog $log) => $log->created_at->format('d M Y H:i'))
            ->addColumn('details', fn (AdminAuditLog $log) => '<a wire:navigate href="'.route('admin.audit-logs.show', $log).'">View details</a>')
            ->filterColumn('admin', fn ($query, string $keyword) => $query->whereHas('admin', fn ($query) => $query->where('name', 'like', "%{$keyword}%")))
            ->orderColumn('admin', fn ($query, string $direction) => $query->orderBy(User::select('name')->whereColumn('users.id', 'admin_audit_logs.admin_id'), $direction))
            ->orderColumn('date', 'created_at $1')
            ->rawColumns(['details'])
            ->toJson();
    }

    public function incidents(Request $request): JsonResponse
    {
        $query = OperationalIncident::query()
            ->when($request->input('status') === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($request->input('status') === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->input('severity')));

        return DataTables::eloquent($query)
            ->addColumn('event', fn (OperationalIncident $incident) => e(str($incident->event_type)->replace('.', ' ')->headline()))
            ->editColumn('severity', fn (OperationalIncident $incident) => '<span class="admin-status admin-status--'.e(strtolower($incident->severity)).'"><i></i>'.e(ucfirst($incident->severity)).'</span>')
            ->addColumn('email', fn (OperationalIncident $incident) => e($incident->submitted_email ?? '—'))
            ->addColumn('status', fn (OperationalIncident $incident) => '<span class="admin-status admin-status--'.($incident->resolved_at ? 'resolved' : 'open').'"><i></i>'.($incident->resolved_at ? 'Resolved' : 'Open').'</span>')
            ->addColumn('date', fn (OperationalIncident $incident) => $incident->occurred_at->format('d M Y H:i'))
            ->addColumn('details', fn (OperationalIncident $incident) => '<a wire:navigate href="'.route('admin.incidents.show', $incident).'">View details</a>')
            ->filterColumn('event', fn ($query, string $keyword) => $query->where('event_type', 'like', "%{$keyword}%"))
            ->filterColumn('email', fn ($query, string $keyword) => $query->where('submitted_email', 'like', "%{$keyword}%"))
            ->filterColumn('status', fn ($query, string $keyword) => str_contains(strtolower($keyword), 'resolved') ? $query->whereNotNull('resolved_at') : $query->whereNull('resolved_at'))
            ->orderColumn('event', 'event_type $1')
            ->orderColumn('email', 'submitted_email $1')
            ->orderColumn('status', 'resolved_at $1')
            ->orderColumn('date', 'occurred_at $1')
            ->rawColumns(['severity', 'status', 'details'])
            ->toJson();
    }
}
