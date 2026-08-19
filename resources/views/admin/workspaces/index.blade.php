@extends('layouts.admin')
@section('title', 'Workspaces')
@section('content')
<form class="admin-filter" method="GET"><input name="search" value="{{ request('search') }}" placeholder="Search workspace name"><button>Filter</button></form>
<section class="admin-card admin-table-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Workspace</th><th>Owner</th><th>Members</th><th>Entries</th><th>Target</th><th></th></tr></thead><tbody>@forelse($workspaces as $workspace)<tr><td><strong>{{ $workspace->name }}</strong><small>{{ $workspace->default_break_minutes }}m {{ $workspace->default_break_type }} break</small></td><td>{{ $workspace->owner?->name }}</td><td>{{ $workspace->users_count }}</td><td>{{ $workspace->hours_entries_count }}</td><td>{{ number_format($workspace->weekly_target_minutes/60,1) }}h</td><td><a href="{{ route('admin.workspaces.show',$workspace) }}">View →</a></td></tr>@empty<tr><td colspan="6">No matching workspaces.</td></tr>@endforelse</tbody></table></div><div class="admin-pagination">{{ $workspaces->links() }}</div></section>
@endsection
