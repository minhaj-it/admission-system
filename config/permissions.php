<?php

/*
 | Permission registry. A permission key is "module.action".
 | - 'modules' appear in the User Access matrix (View / Add / Edit / Delete columns).
 | - 'special' permissions are listed below the matrix.
 | - 'defaults' are the permissions given to the built-in roles when they are created (migration).
 |   Super Admin is not listed: it is implicit, locked and always has every permission.
 */
return [
    'modules' => [
        'dashboard' => ['label' => 'Dashboard', 'actions' => ['view']],
        'institute' => ['label' => 'Institute Profile', 'actions' => ['view', 'edit']],
        'classes' => ['label' => 'Classes & Sessions', 'actions' => ['view', 'add', 'edit', 'del']],
        'roles' => ['label' => 'User Roles', 'actions' => ['view', 'add', 'edit', 'del']],
        'users' => ['label' => 'User List', 'actions' => ['view', 'add', 'edit', 'del']],
        'payment-method' => ['label' => 'Payment Method', 'actions' => ['view', 'edit']],
        'circular' => ['label' => 'Manage Circular', 'actions' => ['view', 'add', 'edit', 'del']],
        'applicant-list' => ['label' => 'Applicant List', 'actions' => ['view']],
        'applicant-edit' => ['label' => 'Applicant Info Update', 'actions' => ['view', 'edit']],
        'registered-accounts' => ['label' => 'Registrar List', 'actions' => ['view', 'edit']],
        'payments' => ['label' => 'Payment List', 'actions' => ['view']],
        'publish' => ['label' => 'Publish Result', 'actions' => ['view', 'edit']],
        'merit' => ['label' => 'Merit List', 'actions' => ['view', 'edit']],
        'audit' => ['label' => 'Audit Log', 'actions' => ['view']],
    ],

    'special' => [
        'applicant-list.export' => 'Export applicant lists (Excel / PDF)',
        'applicant.status' => 'Approve, reject, waitlist or request corrections',
        'document.verify' => 'Verify uploaded documents',
        'payments.verify' => 'Approve or reject manual payments, reconcile',
        'payments.refund' => 'Start refunds',
        'admit-card.publish' => 'Publish, revoke or regenerate admit cards',
        'reports.export' => 'Export reports',
    ],

    'roles' => [
        ['slug' => 'super-admin', 'name' => 'Super Admin', 'description' => 'Full access to every setting and record', 'is_system' => true, 'is_locked' => true],
        ['slug' => 'admin', 'name' => 'Admin', 'description' => 'Manages circulars, applicants, payments, admit cards and reports', 'is_system' => true, 'is_locked' => false],
        ['slug' => 'admission-officer', 'name' => 'Admission Officer', 'description' => 'Admission office / registrar: processes applications and documents', 'is_system' => true, 'is_locked' => false],
        ['slug' => 'accountant', 'name' => 'Accountant', 'description' => 'Views and verifies payments', 'is_system' => true, 'is_locked' => false],
        ['slug' => 'viewer', 'name' => 'Viewer', 'description' => 'Read-only access to lists', 'is_system' => true, 'is_locked' => false],
    ],

    'defaults' => [
        'admin' => [
            'dashboard.view', 'institute.*', 'classes.*', 'circular.*', 'applicant-list.*', 'applicant-edit.*',
            'registered-accounts.*', 'payments.*', 'publish.*', 'merit.*', 'applicant.status', 'document.verify',
            'admit-card.publish', 'reports.export',
        ],
        'admission-officer' => [
            'dashboard.view', 'applicant-list.view', 'applicant-edit.*', 'applicant.status', 'document.verify',
            'registered-accounts.view', 'payments.view', 'admit-card.publish',
        ],
        'accountant' => ['dashboard.view', 'applicant-list.view', 'payments.view', 'payments.verify', 'reports.export'],
        'viewer' => ['dashboard.view', 'circular.view', 'applicant-list.view', 'payments.view', 'registered-accounts.view'],
    ],
];
