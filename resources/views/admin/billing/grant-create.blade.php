@extends('layouts.admin')
@section('title', 'Create access grant')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.grants') }}"><x-admin.icon name="back"/>Back to grants</a>
<section class="admin-card admin-form-card admin-form-card--standalone"><header><div><h2>Grant plan or feature access</h2><p>Leave expiry empty for a permanent grant. Scheduled grants activate automatically.</p></div></header>
<form method="POST" action="{{ route('admin.billing.grants.store') }}">@csrf
    <label>User<select name="user_id" required data-admin-user-select data-url="{{ route('admin.options.users') }}" data-placeholder="Search by name or email">@if($selectedUser)<option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} · {{ $selectedUser->email }}</option>@endif</select></label>
    <label>Grant type<select name="grant_type" required><option value="plan">Plan</option><option value="feature">Individual feature</option></select></label>
    <label>Plan<select name="plan_id"><option value="">Select plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></label>
    <label>Feature<select name="feature_id"><option value="">Select feature</option>@foreach($features as $feature)<option value="{{ $feature->id }}">{{ $feature->name }}</option>@endforeach</select></label>
    <label>Quota override (quota features only)<input type="number" name="quota" min="0" max="1000000"></label>
    <label>Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label>
    <label>Expires at<input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"></label>
    <label>Reason<input name="reason" required minlength="3" maxlength="500" value="{{ old('reason') }}"></label>
    <button>Create grant</button>
</form></section>
@endsection
