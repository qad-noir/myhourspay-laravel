<?php

return [
    'currency' => 'gbp',
    'trial_days' => 14,
    'past_due_grace_days' => 7,
    'launch_grace_days' => 30,
    'business_included_seats' => 5,

    'switches' => [
        'checkout_enabled' => false,
        'paid_enforcement_enabled' => false,
        'monetization_launched_at' => null,
        'entitlement_revision' => 1,
    ],

    'plans' => [
        'free' => [
            'name' => 'Free',
            'description' => 'Core time tracking for one personal workspace.',
            'tier' => 0,
            'purchasable' => false,
            'prices' => [],
        ],
        'pro' => [
            'name' => 'Pro',
            'description' => 'Advanced tools for independent professionals.',
            'tier' => 10,
            'purchasable' => true,
            'prices' => [
                'monthly' => ['amount' => 500, 'stripe_price_id' => env('STRIPE_PRICE_PRO_MONTHLY')],
                'yearly' => ['amount' => 5000, 'stripe_price_id' => env('STRIPE_PRICE_PRO_YEARLY')],
            ],
        ],
        'business' => [
            'name' => 'Business',
            'description' => 'Collaboration, approvals, payroll, and integrations.',
            'tier' => 20,
            'purchasable' => true,
            'prices' => [
                'monthly' => ['amount' => 1500, 'stripe_price_id' => env('STRIPE_PRICE_BUSINESS_MONTHLY')],
                'yearly' => ['amount' => 15000, 'stripe_price_id' => env('STRIPE_PRICE_BUSINESS_YEARLY')],
                'seat_monthly' => ['amount' => 300, 'stripe_price_id' => env('STRIPE_PRICE_BUSINESS_SEAT_MONTHLY')],
                'seat_yearly' => ['amount' => 3000, 'stripe_price_id' => env('STRIPE_PRICE_BUSINESS_SEAT_YEARLY')],
            ],
        ],
    ],

    'features' => [
        'time_tracking' => ['name' => 'Time tracking', 'category' => 'Core', 'mode' => 'free', 'locked' => true, 'plans' => ['free' => true, 'pro' => true, 'business' => true]],
        'workspace_limit' => ['name' => 'Owned workspaces', 'category' => 'Core', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['free' => 1, 'pro' => null, 'business' => null]],
        'dashboard_analytics' => ['name' => 'Dashboard analytics', 'category' => 'Core', 'mode' => 'free', 'locked' => true, 'plans' => ['free' => true, 'pro' => true, 'business' => true]],
        'calendar' => ['name' => 'Hours calendar', 'category' => 'Core', 'mode' => 'free', 'locked' => true, 'plans' => ['free' => true, 'pro' => true, 'business' => true]],
        'csv_export' => ['name' => 'CSV and personal data export', 'category' => 'Exports', 'mode' => 'free', 'locked' => true, 'plans' => ['free' => true, 'pro' => true, 'business' => true]],
        'advanced_reports' => ['name' => 'Advanced reports', 'category' => 'Reports', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'excel_pdf_exports' => ['name' => 'Excel and PDF exports', 'category' => 'Exports', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'export_templates' => ['name' => 'Export templates', 'category' => 'Exports', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'scheduled_reports' => ['name' => 'Scheduled reports', 'category' => 'Reports', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['pro' => 4, 'business' => 31]],
        'earnings' => ['name' => 'Earnings and pay rates', 'category' => 'Pro', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'recurring_schedules' => ['name' => 'Recurring schedules', 'category' => 'Pro', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'smart_reminders' => ['name' => 'Smart reminders', 'category' => 'Pro', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'calendar_integrations' => ['name' => 'Calendar integrations', 'category' => 'Integrations', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['pro' => 2, 'business' => 2]],
        'clients_projects' => ['name' => 'Clients and projects', 'category' => 'Pro', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'invoicing' => ['name' => 'Client invoicing', 'category' => 'Pro', 'mode' => 'premium', 'plans' => ['pro' => true, 'business' => true]],
        'team_members' => ['name' => 'Team members', 'category' => 'Business', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['business' => 5]],
        'roles_permissions' => ['name' => 'Roles and permissions', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'timesheet_approvals' => ['name' => 'Timesheet approvals', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'leave_tracking' => ['name' => 'Leave tracking', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'payroll_exports' => ['name' => 'Payroll exports', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'workspace_audit' => ['name' => 'Workspace audit history', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'custom_branding' => ['name' => 'Custom branding', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
        'api_access' => ['name' => 'API access', 'category' => 'Business', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['business' => 120]],
        'outbound_webhooks' => ['name' => 'Outbound webhooks', 'category' => 'Business', 'mode' => 'premium', 'value_type' => 'quota', 'plans' => ['business' => 10]],
        'priority_support' => ['name' => 'Priority support', 'category' => 'Business', 'mode' => 'premium', 'plans' => ['business' => true]],
    ],
];
