@props(['title', 'description' => '', 'reveal' => false])
<details {{ $attributes->class(['mobile-disclosure']) }} open x-data="responsiveDisclosure(@js($reveal))"
    :open="expanded" @toggle="toggled($el.open)" @invalid.capture="reveal()">
    <summary><span><strong>{{ $title }}</strong>@if($description)<small>{{ $description }}</small>@endif</span><x-dashboard.icon name="chevron-down" :size="18" /></summary>
    <div class="mobile-disclosure__content">{{ $slot }}</div>
</details>
