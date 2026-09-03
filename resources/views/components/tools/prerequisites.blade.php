@props(['title', 'description' => null, 'items'])

<section class="tool-prerequisites" aria-label="{{ $title }}">
    <header><div><p class="dashboard-eyebrow">Readiness</p><h2>{{ $title }}</h2>@if($description)<span>{{ $description }}</span>@endif</div></header>
    <div>
        @foreach($items as $item)
            <article class="{{ $item['complete'] ? 'is-complete' : '' }}">
                <span><x-dashboard.icon :name="$item['complete'] ? 'check' : 'pending'" :size="17" /></span>
                <div><strong>{{ $item['label'] }}</strong>@if(!empty($item['description']))<small>{{ $item['description'] }}</small>@endif</div>
                @if(! $item['complete'] && !empty($item['route']))<a wire:navigate href="{{ $item['route'] }}">{{ $item['action'] ?? 'Set up' }}<x-dashboard.icon name="arrow" :size="14" /></a>@endif
            </article>
        @endforeach
    </div>
</section>
