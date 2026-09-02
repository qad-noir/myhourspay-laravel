@extends('layouts.admin')
@section('title', 'Platform overview')
@section('content')
@php
    $summaryCards = [
        ['Users', $metrics['users'], 'All accounts', 'users', 'neutral'],
        ['Verified', $metrics['verified'], 'Verified emails', 'verified', 'positive'],
        ['Suspended', $metrics['suspended'], 'Restricted accounts', 'suspended', 'warning'],
        ['Workspaces', $metrics['workspaces'], 'Active containers', 'workspaces', 'violet'],
        ['Hours this month', $calculator->formatHumanMinutes($metrics['hours']), $now->format('F Y'), 'hours', 'blue'],
        ['Overtime', $calculator->formatHumanMinutes($metrics['overtime']), 'Positive weekly excess', 'overtime', 'orange'],
        ['Paid breaks', $calculator->formatHumanMinutes($metrics['paid_breaks']), 'Included in worked time', 'paid-break', 'positive'],
        ['Unpaid breaks', $calculator->formatHumanMinutes($metrics['unpaid_breaks']), 'Deducted from worked time', 'unpaid-break', 'warning'],
    ];
@endphp
<section class="admin-metrics" aria-label="Platform summary">
    @foreach($summaryCards as [$label, $value, $support, $icon, $tone])
        <article class="admin-metric admin-metric--{{ $tone }}">
            <div class="admin-metric__top"><span>{{ $label }}</span><i><x-admin.icon :name="$icon" /></i></div>
            <strong>{{ $value }}</strong>
            <small>{{ $support }}</small>
        </article>
    @endforeach
</section>
<div class="admin-columns">
    <section class="admin-card"><header><div><h2>Newest users</h2><p>Five most recently created accounts</p></div><a wire:navigate href="{{ route('admin.users.index') }}">View all →</a></header><div class="admin-list">@forelse($recentUsers as $user)<a wire:navigate href="{{ route('admin.users.show',$user) }}"><span class="admin-avatar">{{ str($user->name)->substr(0,1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><i>{{ $user->created_at->diffForHumans() }}</i></a>@empty<p>No users yet.</p>@endforelse</div></section>
    <section class="admin-card"><header><div><h2>Audit activity</h2><p>Five latest administrative changes</p></div><a wire:navigate href="{{ route('admin.audit-logs.index') }}">View all →</a></header><div class="admin-audit">@forelse($recentAudits as $audit)<div><strong>{{ str($audit->action)->replace('.',' ')->headline() }}</strong><span>{{ $audit->admin?->name ?? 'Deleted administrator' }} · {{ $audit->created_at->diffForHumans() }}</span></div>@empty<p>No admin changes yet.</p>@endforelse</div></section>
</div>
@endsection
