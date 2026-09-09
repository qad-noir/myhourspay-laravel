<?php

return [
    // These workspace destinations are available to every authenticated workspace member.
    'primary' => [
        ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Overview', 'mobile_label' => 'Overview', 'icon' => 'overview'],
        ['route' => 'hours.index', 'active' => ['hours.index', 'hours.entries.*'], 'label' => 'Hours Calendar', 'mobile_label' => 'Hours', 'icon' => 'calendar'],
        ['route' => 'hours.reports.index', 'active' => ['hours.reports.*'], 'label' => 'Reports', 'mobile_label' => 'Reports', 'icon' => 'reports'],
    ],
];
