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
    <x-compact-table id="support-records" :url="route('admin.data.support', request()->only('q','status','priority'))" :columns="[['data'=>'public_id','title'=>'Reference'],['data'=>'customer','title'=>'Customer'],['data'=>'subject','title'=>'Subject'],['data'=>'plan_key','title'=>'Plan'],['data'=>'priority','title'=>'Priority'],['data'=>'status','title'=>'Status'],['data'=>'actions','title'=>'','orderable'=>false]]" />
    <div class="admin-pagination"></div>
</section>
@endsection
