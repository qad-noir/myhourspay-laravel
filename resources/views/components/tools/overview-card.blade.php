@props(['id', 'title', 'description', 'route', 'icon', 'status' => 'Available', 'meta' => null])

@php($state = str($status)->lower()->contains(['locked', 'restricted']) ? 'blocked' : 'ready')
<a id="{{ $id }}" wire:navigate href="{{ $route }}" class="tool-overview-card tool-overview-card--{{ $state }}">
    <span class="tool-overview-card__icon"><x-dashboard.icon :name="$icon" :size="21" /></span>
    <span class="tool-overview-card__copy"><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
    <span class="tool-overview-card__state"><i>{{ $status }}</i>@if($meta)<small>{{ $meta }}</small>@endif</span>
    <x-dashboard.icon name="arrow" :size="18" class="tool-overview-card__arrow" />
</a>
