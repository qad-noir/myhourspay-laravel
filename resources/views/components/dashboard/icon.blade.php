@props(['name', 'size' => 20])
<svg {{ $attributes->merge(['class' => 'dashboard-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('overview')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01" stroke-width="2.6"/>
            @break
        @case('reports')
            <path d="M4 20V10M9.5 20V4M15 20v-7M20 20V7M3 20h18"/>
            @break
        @case('exports')
            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 14h8M8 18h8M8 10h2"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.1A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.1A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.18.37.48.7.86.9.25.13.53.2.82.2H21v4h-.1a1.7 1.7 0 0 0-1.5.9z"/>
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5h5"/>
            @break
        @case('stopwatch')
            <circle cx="12" cy="13" r="8"/><path d="M12 9v4l-2 2M9 2h6M12 2v3M18 7l1.5-1.5"/>
            @break
        @case('trend')
            <path d="m3 17 6-6 4 4 8-9"/><path d="M15 6h6v6"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/>
            @break
        @case('team')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18"/>
            @break
        @case('billing')
            <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h3"/>
            @break
        @case('clients')
            <circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 8h5M18.5 5.5v5"/>
            @break
        @case('earnings')
            <circle cx="12" cy="12" r="9"/><path d="M15.5 8.5c-.7-.8-1.8-1.2-3-1.2-1.7 0-3 1-3 2.3 0 3.5 6 1.5 6 4.8 0 1.3-1.3 2.3-3 2.3-1.4 0-2.6-.5-3.3-1.4M12.5 5v14"/>
            @break
        @case('schedules')
            <rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M16 3v4M8 3v4M3 10h18M8 15h4M8 18h7"/>
            @break
        @case('reminders')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
            @break
        @case('invoice')
            <path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h3"/>
            @break
        @case('timesheet')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>
            @break
        @case('leave')
            <path d="M12 21a9 9 0 1 0-9-9c0 2.3.9 4.5 2.4 6.1"/><path d="M12 7v5l3 2M3 21l2.4-2.9L8 21"/>
            @break
        @case('payroll')
            <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M7 9h10M7 13h4M15 13h2M7 16h2M13 16h4"/>
            @break
        @case('branding')
            <path d="M12 3a9 9 0 1 0 9 9c0-1.1-.9-2-2-2h-1.5a2.5 2.5 0 0 1-2.5-2.5V6c0-1.7-1.3-3-3-3Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="16" r="1"/>
            @break
        @case('activity')
            <path d="M3 12h4l2.5-7 5 14 2.5-7h4"/>
            @break
        @case('webhook')
            <path d="M16 8a4 4 0 1 0-7.5 2M8 16a4 4 0 1 0 7.5-2M9 9l6 6"/><path d="m13 8 3-1 1 3M11 16l-3 1-1-3"/>
            @break
        @case('support')
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3v-7a4 4 0 0 1-1-2.7V7a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4Z"/><path d="M8 8h8M8 12h5"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('arrow')
            <path d="M5 12h14M14 7l5 5-5 5"/>
            @break
        @case('pending')
            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
            @break
        @case('more')
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/>
            @break
    @endswitch
</svg>
