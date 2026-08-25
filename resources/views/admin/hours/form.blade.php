@extends('layouts.admin')
@section('title', $entry ? 'Edit hours entry' : 'Create hours entry')
@section('content')
<a wire:navigate class="admin-context-back" href="{{ route('admin.hours.index') }}"><svg viewBox="0 0 20 20"><path d="m12.5 5-5 5 5 5"/></svg>Back to hours</a>
<section class="admin-card admin-form-card admin-form-card--standalone">
    <form method="POST" data-admin-hours-form action="{{ $entry ? route('admin.hours.update', $entry) : route('admin.hours.store') }}">
        @csrf
        @if($entry) @method('PUT') @endif
        <label>User
            <select name="user_id" required data-admin-user-select data-hours-user data-url="{{ route('admin.options.users') }}" data-placeholder="Search by name or email">
                @if($selectedUser)<option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} · {{ $selectedUser->email }}</option>@endif
            </select>
            <small class="admin-field-help">Enter at least two characters to find a user.</small>
        </label>
        <label>Workspace
            <select name="workspace_id" required data-admin-workspace-select data-hours-workspace data-url-template="{{ route('admin.options.user-workspaces', ['user' => '__USER__']) }}" data-placeholder="Select a workspace" @disabled(!$selectedUser)>
                @if($selectedWorkspace)<option value="{{ $selectedWorkspace->id }}" selected>{{ $selectedWorkspace->name }}</option>@endif
            </select>
            <small class="admin-field-help" data-hours-workspace-help>{{ $selectedUser ? 'Only this user’s active workspaces are available.' : 'Select a user first.' }}</small>
        </label>
        <label>Date<input type="date" name="work_date" value="{{ old('work_date', $entry?->work_date?->format('Y-m-d')) }}" required></label>
        <label>Start<input type="time" name="start_time" value="{{ old('start_time', $entry ? substr($entry->start_time, 0, 5) : '09:00') }}" required></label>
        <label>End<input type="time" name="end_time" value="{{ old('end_time', $entry ? substr($entry->end_time, 0, 5) : '17:30') }}" required></label>
        <label>Break type<select name="break_type"><option value="unpaid" @selected(old('break_type', $entry?->break_type) === 'unpaid')>Unpaid</option><option value="paid" @selected(old('break_type', $entry?->break_type) === 'paid')>Paid</option></select></label>
        <label>Break minutes<input type="number" name="break_minutes" value="{{ old('break_minutes', $entry?->break_minutes ?? 30) }}" min="0" max="1439"></label>
        <label>Notes<textarea name="notes">{{ old('notes', $entry?->notes) }}</textarea></label>
        <button>{{ $entry ? 'Save changes' : 'Create entry' }}</button>
    </form>
</section>
@endsection
