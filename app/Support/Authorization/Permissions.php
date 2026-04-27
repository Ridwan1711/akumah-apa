<?php

namespace App\Support\Authorization;

final class Permissions
{
    private function __construct() {}

    public const DASHBOARD_ADMIN = 'dashboard.admin.view';

    public const DASHBOARD_GURU = 'dashboard.guru.view';

    public const DASHBOARD_SANTRI = 'dashboard.santri.view';

    public const DASHBOARD_WALI = 'dashboard.wali.view';

    public const INVOICE_VIEW = 'invoice.view';

    public const INVOICE_VIEW_ALL = 'invoice.view_all';

    public const INVOICE_VIEW_NON_PENGURUS = 'invoice.view_non_pengurus';

    public const INVOICE_VIEW_PENGURUS_DIVISION = 'invoice.view_pengurus_division';

    public const INVOICE_CREATE = 'invoice.create';

    public const INVOICE_CANCEL = 'invoice.cancel';

    public const PAYMENT_VIEW = 'payment.view';

    public const PAYMENT_VERIFY = 'payment.verify';

    public const PAYMENT_REJECT = 'payment.reject';

    public const PAYMENT_REPORT_VIEW = 'payment.report.view';

    public const USER_MANAGEMENT_VIEW = 'user.management.view';

    public const USER_MANAGEMENT_EDIT = 'user.management.edit';

    public const LEAVE_PERMISSION_VIEW = 'leave_permission.view';

    public const LEAVE_PERMISSION_APPROVE = 'leave_permission.approve';

    /** Log audit: tagihan, pembayaran, diskon, jenis bayar (semua user). */
    public const AUDIT_LOG_VIEW_FINANCE = 'audit_log.view_finance';

    /** Log audit: santri, akademik, operasional (bukan domain keuangan di atas). */
    public const AUDIT_LOG_VIEW_AKADEMIK = 'audit_log.view_akademik';
}
