@props(['title' => 'No hours recorded yet', 'description' => 'Add your first workday to start building your weekly and monthly totals.', 'compact' => false])
<div class="dashboard-empty {{ $compact ? 'dashboard-empty--compact' : '' }}"><span><x-dashboard.icon name="clock" /></span><h3>{{ $title }}</h3><p>{{ $description }}</p>@if(isset($action))<div>{{ $action }}</div>@endif</div>
