<nav class="mobile-navigation" aria-label="Primary mobile navigation" data-mobile-navigation>
    @foreach(config('navigation.primary') as $item)
        <a wire:navigate href="{{ route($item['route']) }}" aria-label="{{ __($item['label']) }}" @if(request()->routeIs(...$item['active'])) aria-current="page" @endif>
            <span class="mobile-navigation__icon"><x-dashboard.icon :name="$item['icon']" :size="21" /></span>
            <span class="mobile-navigation__label">{{ __($item['mobile_label']) }}</span>
        </a>
    @endforeach
    <button type="button" data-sidebar-open aria-label="More: open navigation menu" aria-controls="dashboard-sidebar" aria-expanded="false">
        <span class="mobile-navigation__icon"><x-dashboard.icon name="more" :size="21" /></span>
        <span class="mobile-navigation__label">{{ __('More') }}</span>
    </button>
</nav>
