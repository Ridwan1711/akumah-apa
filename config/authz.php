<?php

use App\Models\Role;
use App\Support\Authorization\Permissions;

return [
    'role_permissions' => [
        Role::SUPER_ADMIN => ['*'],

        Role::ADMIN_AKADEMIK => [
            Permissions::DASHBOARD_ADMIN,
            Permissions::DASHBOARD_GURU,
            Permissions::NOTIFICATION_MANUAL_SEND,
            Permissions::LEAVE_PERMISSION_VIEW,
            Permissions::LEAVE_PERMISSION_APPROVE,
            Permissions::AUDIT_LOG_VIEW_AKADEMIK,
            'kitab_grades.view_all',
        ],

        Role::ADMIN_KEUANGAN => [
            Permissions::DASHBOARD_ADMIN,
            Permissions::AUDIT_LOG_VIEW_FINANCE,
            Permissions::INVOICE_VIEW,
            Permissions::INVOICE_VIEW_NON_PENGURUS,
            Permissions::INVOICE_CREATE,
            Permissions::INVOICE_CANCEL,
            Permissions::PAYMENT_VIEW,
            Permissions::PAYMENT_VERIFY,
            Permissions::PAYMENT_REJECT,
            Permissions::PAYMENT_REPORT_VIEW,
        ],

        Role::MUSYRIF => [
            Permissions::INVOICE_VIEW,
            Permissions::INVOICE_VIEW_NON_PENGURUS,
        ],

        Role::GURU => [
            Permissions::DASHBOARD_GURU,
        ],

        Role::SANTRI => [
            Permissions::DASHBOARD_SANTRI,
        ],

        Role::WALI_SANTRI => [
            Permissions::DASHBOARD_WALI,
        ],
    ],

    'scope_keys' => [
        'division_code',
        'asrama_id',
    ],
];

