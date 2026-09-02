@props(['name', 'size' => 19])
<svg {{ $attributes->merge(['class' => 'admin-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('overview') <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/> @break
        @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/> @break
        @case('workspaces') <path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M9 18h6"/> @break
        @case('hours') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('billing') <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h3"/> @break
        @case('support') <path d="M21 15a4 4 0 0 1-4 4H8l-5 3v-7a4 4 0 0 1-1-2.7V7a4 4 0 0 1 4-4h11a4 4 0 0 1 4 4z"/><path d="M8 8h8M8 12h5"/> @break
        @case('audit') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/> @break
        @case('incidents') <path d="M10.3 3.6 2.5 17a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/> @break
        @case('trash') <path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v5M14 11v5"/> @break
        @case('back') <path d="m15 18-6-6 6-6"/> @break
        @case('more') <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/> @break
        @case('filter') <path d="M4 6h16M7 12h10M10 18h4"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('verified') <path d="m8.5 12 2.2 2.2 4.8-5.1"/><path d="M12 3 5 6v5c0 4.6 2.9 8.4 7 10 4.1-1.6 7-5.4 7-10V6l-7-3Z"/> @break
        @case('suspended') <circle cx="12" cy="12" r="9"/><path d="m7 7 10 10"/> @break
        @case('overtime') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M18.5 5.5 20 4"/> @break
        @case('paid-break') <path d="M6 8h10v5a5 5 0 0 1-5 5 5 5 0 0 1-5-5V8Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8 4v2M12 4v2"/> @break
        @case('unpaid-break') <path d="M6 8h10v5a5 5 0 0 1-5 5 5 5 0 0 1-5-5V8Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M5 5l14 14"/> @break
        @case('trials') <path d="M9 3h6M10 3v5l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M8 15h8"/> @break
        @case('past-due') <rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M16 14v2M16 18h.01"/> @break
        @case('conversion') <path d="M4 17 10 11l4 4 6-8"/><path d="M15 7h5v5"/> @break
        @case('churn') <path d="M4 7h11a5 5 0 0 1 0 10H9"/><path d="m8 3-4 4 4 4"/> @break
        @case('seats') <circle cx="9" cy="8" r="3"/><path d="M3 19v-1a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v1M16 8h5M18.5 5.5v5"/> @break
        @case('grants') <path d="M20 12v8H4v-8M2 8h20v4H2zM12 8v12"/><path d="M12 8H7.5A2.5 2.5 0 1 1 10 5.5L12 8Zm0 0h4.5A2.5 2.5 0 1 0 14 5.5L12 8Z"/> @break
        @case('expiring') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M8 2h8"/> @break
        @case('usage') <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/> @break
        @case('view') <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/> @break
        @case('edit') <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/> @break
        @case('restore') <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/> @break
        @case('delete') <path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v5M14 11v5"/> @break
        @case('suspend') <circle cx="12" cy="12" r="9"/><path d="M7 12h10"/> @break
        @case('activate') <circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/> @break
        @case('verify') <path d="m8.5 12 2.2 2.2 4.8-5.1"/><path d="M12 3 5 6v5c0 4.6 2.9 8.4 7 10 4.1-1.6 7-5.4 7-10V6l-7-3Z"/> @break
        @case('unverify') <path d="M12 3 5 6v5c0 4.6 2.9 8.4 7 10 4.1-1.6 7-5.4 7-10V6l-7-3Z"/><path d="m8.5 9 7 7M15.5 9l-7 7"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/> @break
        @case('reset') <path d="M12 3a9 9 0 1 1-8.5 6"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/> @break
        @case('sync') <path d="M20 7h-5V2"/><path d="M20 7a8 8 0 0 0-14-2M4 17h5v5"/><path d="M4 17a8 8 0 0 0 14 2"/> @break
        @case('cancel') <circle cx="12" cy="12" r="9"/><path d="m8 8 8 8M16 8l-8 8"/> @break
        @case('revoke') <path d="M12 3 5 6v5c0 4.6 2.9 8.4 7 10 4.1-1.6 7-5.4 7-10V6l-7-3Z"/><path d="M8 12h8"/> @break
    @endswitch
</svg>
