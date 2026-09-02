@extends('layouts.admin')
@section('title','Support queue')
@section('content')
<section class="admin-card admin-table-card">
    <header><div><h2>Customer support requests</h2><p>Business requests enter with priority status automatically.</p></div></header>
    <form method="GET" class="admin-filter-form">
        <x-admin.filter-bar title="Find a request" description="Search the queue, then combine state and priority when triaging work.">
            <label class="admin-filter__field admin-filter__field--wide"><span>Search</span><input name="q" value="{{ request('q') }}" placeholder="Reference, subject or email"></label>
            <label class="admin-filter__field"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach(['open','in_progress','closed'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
            <label class="admin-filter__field"><span>Priority</span><select name="priority"><option value="">All priorities</option>@foreach(['normal','priority','urgent'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ str($priority)->headline() }}</option>@endforeach</select></label>
            <button class="admin-filter__apply">Apply filters</button>
            @if(request()->hasAny(['q','status','priority']))<a wire:navigate class="admin-filter__reset" href="{{ route('admin.support.index') }}">Clear</a>@endif
        </x-admin.filter-bar>
    </form>
    <div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>Reference</th><th>Customer</th><th>Subject</th><th>Plan</th><th>Priority</th><th>Status</th><th></th></tr></thead><tbody>@forelse($requests as $item)<tr><td>{{ str($item->public_id)->limit(13) }}</td><td><strong>{{ $item->user?->name }}</strong><small>{{ $item->user?->email }}</small></td><td>{{ $item->subject }}</td><td>{{ str($item->plan_key)->headline() }}</td><td>{{ str($item->priority)->headline() }}</td><td>{{ str($item->status)->headline() }}</td><td><a wire:navigate href="{{ route('admin.support.show',$item) }}">Review</a></td></tr>@empty<tr><td colspan="7">No support requests match these filters.</td></tr>@endforelse</tbody></table></div>
    <div class="admin-pagination">{{ $requests->links() }}</div>
</section>
@endsection
