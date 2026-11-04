<?php

/*
 | Admin sidebar (same entries and order as the supplied UI).
 | 'route' => null means the page is not built yet: it is shown greyed out and does nothing.
 | 'can'   => permission key used from milestone 3 (roles & permissions); unused until then.
 */
return [
    'admin' => [
        ['id' => 'dashboard', 'icon' => 'bi-pie-chart', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'can' => 'dashboard.view'],
        ['title' => 'ADMISSION'],
        ['id' => 'settings', 'icon' => 'bi-gear', 'label' => 'Settings', 'children' => [
            ['label' => 'Institute Profile', 'route' => 'admin.institute.edit', 'active' => 'admin.institute.*', 'can' => 'institute.view'],
            ['label' => 'Classes & Sessions', 'route' => 'admin.master.index', 'active' => 'admin.master.*', 'can' => 'classes.view'],
            ['label' => 'User Roles', 'route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'can' => 'roles.view'],
            ['label' => 'User List', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'can' => 'users.view'],
            ['label' => 'Payment Method', 'route' => 'admin.payment-methods.index', 'active' => 'admin.payment-methods.*', 'can' => 'payment-method.view'],
            ['label' => 'Audit Log', 'route' => 'admin.audit.index', 'active' => 'admin.audit.*', 'can' => 'audit.view'],
        ]],
        ['id' => 'circular', 'icon' => 'bi-clipboard-check', 'label' => 'Circular', 'children' => [
            ['label' => 'Manage Circular', 'route' => 'admin.circulars.index', 'active' => 'admin.circulars.*', 'can' => 'circular.view'],
        ]],
        ['id' => 'applicant', 'icon' => 'bi-people', 'label' => 'Manage Applicant', 'children' => [
            ['label' => 'Applicant List', 'route' => 'admin.applicants.index', 'active' => 'admin.applicants.*', 'can' => 'applicant-list.view'],
            ['label' => 'Applicant Info Update', 'route' => null, 'can' => 'applicant-edit.view'],
        ]],
        ['id' => 'registrar', 'icon' => 'bi-person-lines-fill', 'label' => 'Registrar List', 'route' => null, 'can' => 'registered-accounts.view'],
        ['id' => 'payments', 'icon' => 'bi-credit-card-2-front', 'label' => 'Payment List', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'can' => 'payments.view'],
        ['id' => 'result', 'icon' => 'bi-display', 'label' => 'Result Update', 'children' => [
            ['label' => 'Publish Result', 'route' => null, 'can' => 'publish.view'],
            ['label' => 'Merit List', 'route' => null, 'can' => 'merit.view'],
        ]],
    ],
];
