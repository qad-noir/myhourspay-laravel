@extends('layouts.admin')
@section('title', 'Platform overview')
@section('content')
<section class="admin-metrics">
    @foreach([['Users',$metrics['users'],'All accounts'],['Verified',$metrics['verified'],'Verified emails'],['Suspended',$metrics['suspended'],'Restricted accounts'],['Workspaces',$metrics['workspaces'],'Active containers'],['Hours this month',$calculator->formatHumanMinutes($metrics['hours']),$now->format('F Y')],['Overtime',$calculator->formatHumanMinutes($metrics['overtime']),'Positive weekly excess'],['Paid breaks',$calculator->formatHumanMinutes($metrics['paid_breaks']),'Included'],['Unpaid breaks',$calculator->formatHumanMinutes($metrics['unpaid_breaks']),'Deducted']] as [$label,$value,$support])
        <article><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $support }}</small></article>
    @endforeach
</section>
<div class="admin-columns">
    <section class="admin-card"><header><div><h2>Newest users</h2><p>Recently created accounts</p></div><a href="{{ route('admin.users.index') }}">View all →</a></header><div class="admin-list">@forelse($recentUsers as $user)<a href="{{ route('admin.users.show',$user) }}"><span class="admin-avatar">{{ str($user->name)->substr(0,1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><i>{{ $user->created_at->diffForHumans() }}</i></a>@empty<p>No users yet.</p>@endforelse</div></section>
    <section class="admin-card"><header><div><h2>Audit activity</h2><p>Recent admin changes</p></div></header><div class="admin-audit">@forelse($recentAudits as $audit)<div><strong>{{ str($audit->action)->replace('.',' ')->headline() }}</strong><span>{{ $audit->admin?->name }} · {{ $audit->created_at->diffForHumans() }}</span></div>@empty<p>No admin changes yet.</p>@endforelse</div></section>
</div>
@endsection
