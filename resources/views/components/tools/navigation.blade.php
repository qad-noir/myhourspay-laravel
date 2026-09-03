@props(['area', 'access', 'canPayroll' => true])

@php
    $items = $area === 'pro'
        ? [
            ['overview', 'Overview', 'overview', 'pro.index', 'pro.index', null],
            ['clients', 'Clients', 'clients', 'pro.clients.index', 'pro.clients.*', 'clients_projects'],
            ['earnings', 'Earnings', 'earnings', 'pro.earnings.index', 'pro.earnings.*', 'earnings'],
            ['schedules', 'Schedules', 'schedules', 'pro.schedules.index', 'pro.schedules.*', 'recurring_schedules'],
            ['reminders', 'Reminders', 'reminders', 'pro.reminders.index', 'pro.reminders.*', 'smart_reminders'],
            ['reports', 'Reports', 'reports', 'pro.reports.index', 'pro.reports.*', 'export_templates'],
            ['calendars', 'Calendars', 'calendar', 'pro.calendars.index', 'pro.calendars.*', 'calendar_integrations'],
            ['invoices', 'Invoices', 'invoice', 'pro.invoices.index', 'pro.invoices.*', 'invoicing'],
        ]
        : [
            ['overview', 'Overview', 'overview', 'business.index', 'business.index', null],
            ['team', 'Team', 'team', 'business.team.index', 'business.team.*', 'team_members'],
            ['timesheets', 'Timesheets', 'timesheet', 'business.timesheets.index', 'business.timesheets.*', 'timesheet_approvals'],
            ['leave', 'Leave', 'leave', 'business.leave.index', 'business.leave.*', 'leave_tracking'],
            ['payroll', 'Payroll', 'payroll', 'business.payroll.index', 'business.payroll.*', 'payroll_exports'],
            ['branding', 'Branding', 'branding', 'business.branding.index', 'business.branding.*', 'custom_branding'],
            ['activity', 'Activity', 'activity', 'business.activity.index', 'business.activity.*', 'workspace_audit'],
            ['webhooks', 'Webhooks', 'webhook', 'business.webhooks.index', 'business.webhooks.*', 'outbound_webhooks'],
            ['support', 'Support', 'support', 'business.support.index', 'business.support.*', 'priority_support'],
        ];
@endphp

<nav class="tool-module-nav" data-tool-area="{{ $area }}" aria-label="{{ str($area)->headline() }} tools">
    <div class="tool-module-nav__track">
        @foreach($items as [$key, $label, $icon, $route, $pattern, $feature])
            @php
                $locked = $feature && ! $access->get($feature, false);
                $restricted = $area === 'business' && $key === 'payroll' && ! $canPayroll;
                $active = request()->routeIs($pattern);
            @endphp
            <a wire:navigate href="{{ route($route) }}" @if($active) aria-current="page" @endif class="{{ $locked ? 'is-locked' : '' }} {{ $restricted ? 'is-restricted' : '' }}">
                <span class="tool-module-nav__icon"><x-dashboard.icon :name="$icon" :size="18" /></span>
                <span><strong>{{ $label }}</strong>@if($restricted || $locked)<small>{{ $restricted ? 'Role restricted' : 'Locked' }}</small>@endif</span>
            </a>
        @endforeach
    </div>
</nav>
