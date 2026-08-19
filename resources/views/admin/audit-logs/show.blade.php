@extends('layouts.admin')
@section('title', 'Audit detail')
@section('content')
<a class="admin-context-back" href="{{ route('admin.audit-logs.index') }}"><svg viewBox="0 0 20 20"><path d="m12.5 5-5 5 5 5"/></svg>Back to audit logs</a>
<section class="admin-card admin-log-detail">
    <dl>
        <dt>Action</dt><dd>{{ $auditLog->action }}</dd>
        <dt>Administrator</dt><dd>{{ $auditLog->admin?->name ?? 'Deleted administrator' }}</dd>
        <dt>Target</dt><dd>{{ $auditLog->target_type ? class_basename($auditLog->target_type) : 'Record' }} #{{ $auditLog->target_id }}</dd>
        <dt>IP address</dt><dd>{{ $auditLog->ip_address }}</dd>
        <dt>Date</dt><dd>{{ $auditLog->created_at }}</dd>
    </dl>
    <div class="admin-json">
        <article><h2>Before</h2><pre>{{ json_encode($auditLog->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></article>
        <article><h2>After</h2><pre>{{ json_encode($auditLog->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></article>
    </div>
</section>
@endsection
