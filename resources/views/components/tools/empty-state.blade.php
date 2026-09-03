@props(['icon' => 'pending', 'title', 'description', 'route' => null, 'action' => null])

<div class="tool-empty-state">
    <span><x-dashboard.icon :name="$icon" :size="21" /></span>
    <div><strong>{{ $title }}</strong><p>{{ $description }}</p></div>
    @if($route && $action)<a wire:navigate class="dashboard-button dashboard-button--secondary" href="{{ $route }}">{{ $action }}<x-dashboard.icon name="arrow" :size="14" /></a>@endif
</div>
