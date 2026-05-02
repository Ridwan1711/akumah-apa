<?php

namespace App\Support\Audit;

use App\Models\User;
use App\Support\Authorization\Permissions;

final class AuditLogModules
{
    /** Modul yang ditulis trait Auditable untuk domain keuangan. */
    public const FINANCE = ['invoice', 'payment', 'studentdiscount', 'paymenttype'];

    /** Modul akademik, santri, asrama, izin, pelanggaran, wali, kehadiran pelajaran. */
    public const AKADEMIK_OPERASIONAL = ['student', 'guardian', 'score', 'leavepermission', 'studentviolation', 'dormassignment', 'lessonattendance'];

    private function __construct() {}

    /**
     * @return list<string>|null null bila tidak dibatasi modul (super admin).
     */
    public static function allowedModuleNames(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $modules = [];
        if ($user->hasPermission(Permissions::AUDIT_LOG_VIEW_FINANCE)) {
            $modules = array_merge($modules, self::FINANCE);
        }
        if ($user->hasPermission(Permissions::AUDIT_LOG_VIEW_AKADEMIK)) {
            $modules = array_merge($modules, self::AKADEMIK_OPERASIONAL);
        }

        return array_values(array_unique($modules));
    }

    public static function scopeDescription(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return 'Menampilkan aktivitas di seluruh modul yang tercatat (termasuk keuangan, akademik, operasional, dan lainnya).';
        }

        $finance = $user->hasPermission(Permissions::AUDIT_LOG_VIEW_FINANCE);
        $akademik = $user->hasPermission(Permissions::AUDIT_LOG_VIEW_AKADEMIK);

        if ($finance && $akademik) {
            return 'Menampilkan aktivitas modul keuangan, akademik, dan operasional.';
        }
        if ($finance) {
            return 'Menampilkan aktivitas keuangan dari semua pengguna (tagihan, pembayaran, jenis pembayaran, diskon santri).';
        }
        if ($akademik) {
            return 'Menampilkan aktivitas akademik & operasional (santri, wali, nilai diniyah, kehadiran pelajaran, izin pulang, pelanggaran, asrama).';
        }

        return '';
    }
}
