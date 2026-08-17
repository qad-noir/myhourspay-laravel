@props(['dark' => false, 'compact' => false])

<span {{ $attributes->class(['brand-logo', 'brand-logo--dark' => $dark]) }}>
    <span class="brand-logo__mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.8" />
            <path d="M12 7.8v4.6l3 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="brand-logo__dot"></span>
    </span>
    @unless ($compact)
        <span class="brand-logo__word"><span>myhours</span><strong>pay</strong></span>
    @endunless
</span>
