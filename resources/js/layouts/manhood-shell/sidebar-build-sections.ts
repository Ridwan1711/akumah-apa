import {
    AlertTriangle,
    ArrowUpDown,
    Banknote,
    Bell,
    BookOpen,
    BookOpenCheck,
    Building,
    CalendarClock,
    CalendarDays,
    ClipboardList,
    CreditCard,
    FileText,
    GraduationCap,
    Home,
    KeyRound,
    LayoutGrid,
    Layers,
    ListChecks,
    PieChart,
    Receipt,
    School,
    ScrollText,
    Shield,
    User,
    UserCheck,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';

import { can, canAny } from '@/lib/authz';
import type { Auth, NavItem } from '@/types';

/** Satu blok menu di sidebar (mis. Data Master, Keuangan). */
export type NavSection = {
    label: string;
    items: NavItem[];
};

const ADMIN_ROLES = ['super_admin', 'admin_akademik'] as const;
const KEUANGAN_ROLES = ['super_admin', 'admin_keuangan'] as const;
const MUSYRIF_ROLES = ['musyrif'] as const;

const FINANCE_ROUTE_PERMISSIONS = ['invoice.view', 'payment.view', 'payment.report.view'] as const;

function hasAnyRole(userRoleNames: string[], allowedRoles: readonly string[]): boolean {
    return allowedRoles.some((allowedRole) => userRoleNames.includes(allowedRole));
}

export function getUserRoleNames(user: Auth['user']): string[] {
    const directRole = user.role?.name ? [user.role.name] : [];
    const relationRoles =
        'roles' in user && Array.isArray(user.roles)
            ? user.roles
                  .map((role) => role?.name)
                  .filter((roleName): roleName is string => !!roleName)
            : [];

    return Array.from(new Set([...directRole, ...relationRoles]));
}

export function formatRoleLabel(roleNames: string[]): string {
    if (roleNames.length === 0) return 'Pengguna';

    const titleCaseRole = (roleName: string) =>
        roleName
            .replace(/_/g, ' ')
            .split(' ')
            .filter(Boolean)
            .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
            .join(' ');

    return roleNames.map(titleCaseRole).join(' · ');
}

const dataMasterNav: NavItem[] = [
    { title: 'Tahun Ajaran', href: '/admin/academic-years', icon: CalendarDays },
    { title: 'Wali Santri', href: '/admin/guardians', icon: UserCheck },
    { title: 'Data Santri', href: '/admin/students', icon: Users },
    { title: 'Pengurus Santri', href: '/admin/student-positions', icon: Shield },
    { title: 'Generate Akun', href: '/admin/account-generator', icon: KeyRound },
    { title: 'Kelas Diniyah', href: '/admin/diniyah-classes', icon: GraduationCap },
    { title: 'Enroll Kelas Santri', href: '/admin/student-enrollments', icon: UserPlus },
    { title: 'Master Tingkat Formal', href: '/admin/tingkat-sekolahs', icon: Layers },
    { title: 'Enroll Tingkat Formal', href: '/admin/formal-tingkat/assign', icon: School },
    { title: 'Asrama', href: '/admin/asrama', icon: Building },
];

const akademikNav: NavItem[] = [
    { title: 'Mata Pelajaran Kitab', href: '/admin/kitab-subjects', icon: BookOpen },
    { title: 'Mapping Mapel-Tingkat', href: '/admin/subject-level-mappings', icon: ClipboardList },
    { title: 'Subject Setting', href: '/admin/subject-settings', icon: ClipboardList },
    { title: 'Komponen Penilaian', href: '/admin/assessment-components', icon: ListChecks },
    { title: 'Manajemen Guru', href: '/admin/teachers', icon: Users },
    { title: 'Penugasan Guru', href: '/admin/teaching-assignments', icon: UserPlus },
    { title: 'Penguji Baca Kitab', href: '/admin/kitab-reading-examiners', icon: UserCheck },
    { title: 'Surat Keterangan', href: '/admin/role-certificates', icon: ScrollText },
    { title: 'Jadwal Kitab', href: '/admin/schedules', icon: CalendarClock },
    { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets', icon: CalendarClock },
    { title: 'Kehadiran Santri', href: '/admin/attendances', icon: CalendarDays },
    { title: 'Nilai Kitab', href: '/admin/kitab-grades', icon: ClipboardList },
    { title: 'Nilai Baca Kitab', href: '/admin/kitab-reading-assessments', icon: BookOpenCheck },
    { title: 'Raport', href: '/admin/report-cards', icon: ScrollText },
    { title: 'Kenaikan Kelas', href: '/admin/class-promotion', icon: ArrowUpDown },
];

const musyrifOperasionalNav: NavItem[] = [
    { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
    { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
];

const santriNav: NavItem[] = [
    { title: 'Jadwal', href: '/santri/schedule', icon: CalendarDays },
    { title: 'Kehadiran', href: '/santri/attendances', icon: CalendarClock },
    { title: 'Nilai Kitab', href: '/santri/grades', icon: ClipboardList },
    { title: 'Pelanggaran', href: '/santri/violations', icon: AlertTriangle },
    { title: 'Profil', href: '/santri/profile', icon: User },
];

const waliNav: NavItem[] = [
    { title: 'Data Anak', href: '/wali/children', icon: Users },
    { title: 'Tagihan', href: '/wali/invoices', icon: Banknote },
    { title: 'Riwayat Bayar', href: '/wali/payment-history', icon: Receipt },
];

const waliKelasNav: NavItem[] = [
    { title: 'Review Nilai Kelas', href: '/wali-kelas/grade-reviews', icon: ClipboardList },
    { title: 'Rekap Kenaikan Kelas', href: '/wali-kelas/class-promotion-recaps', icon: ArrowUpDown },
    { title: 'Raport Kelas', href: '/wali-kelas/report-cards', icon: ScrollText },
];

function financeItems(auth: Auth, isSuperAdmin: boolean): NavItem[] {
    const items: NavItem[] = [];
    if (canAny(auth, ['invoice.view'])) {
        items.push({ title: 'Tagihan', href: '/admin/invoices', icon: Banknote });
    }
    if (canAny(auth, ['payment.view'])) {
        items.push({ title: 'Pembayaran', href: '/admin/payments', icon: CreditCard });
    }
    if (canAny(auth, [...FINANCE_ROUTE_PERMISSIONS])) {
        items.push(
            { title: 'Diskon Santri', href: '/admin/student-discounts', icon: Wallet },
            { title: 'Jenis Tagihan', href: '/admin/payment-types', icon: ClipboardList },
        );
    }
    if (canAny(auth, ['payment.report.view'])) {
        items.push({ title: 'Laporan Keuangan', href: '/admin/payment-reports', icon: PieChart });
    }
    if (can(auth, 'audit_log.view_finance') && !isSuperAdmin) {
        items.push({ title: 'Log Aktivitas Keuangan', href: '/admin/audit-logs', icon: FileText });
    }

    return items;
}

/** Susun blok sidebar dari auth + role + flag catatan guru/wali kelas. */
export function buildSidebarSections(
    auth: Auth,
    userRoleNames: string[],
    hasGuruRecord: boolean,
    hasWaliKelasRecord: boolean,
    hasKitabReadingExaminerRecord: boolean,
): NavSection[] {
    const isAdmin = hasAnyRole(userRoleNames, ADMIN_ROLES);
    const hasFinanceAccess =
        canAny(auth, [...FINANCE_ROUTE_PERMISSIONS]) || hasAnyRole(userRoleNames, KEUANGAN_ROLES);
    const isSuperAdmin = userRoleNames.includes('super_admin');
    const isMusyrif = hasAnyRole(userRoleNames, MUSYRIF_ROLES);
    const isSantri = userRoleNames.includes('santri');
    const isWaliSantri = userRoleNames.includes('wali_santri');
    const canAccessKitabGrades =
        canAny(auth, ['dashboard.guru.view', 'dashboard.admin.view']) ||
        isAdmin ||
        hasGuruRecord ||
        hasKitabReadingExaminerRecord;
    const canAccessRaportKelas = hasWaliKelasRecord;

    const sections: NavSection[] = [
        {
            label: 'Utama',
            items: [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }],
        },
    ];

    if (isAdmin) {
        sections.push({ label: 'Data Master', items: [...dataMasterNav] });
        sections.push({ label: 'Akademik', items: [...akademikNav] });
        sections.push({
            label: 'Operasional',
            items: [
                ...musyrifOperasionalNav,
                ...(can(auth, 'notification.manual.send')
                    ? [{ title: 'Kirim Notifikasi', href: '/admin/notifications/manual', icon: Bell }]
                    : []),
            ],
        });

        const sistemItems: NavItem[] = isSuperAdmin
            ? [
                  { title: 'Manajemen User', href: '/admin/users', icon: Shield },
                  { title: 'Log Sistem', href: '/admin/system-logs', icon: FileText },
                  { title: 'Laravel Log', href: '/admin/laravel-logs', icon: FileText },
                  { title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText },
              ]
            : can(auth, 'audit_log.view_akademik')
              ? [
                    { title: 'Log Sistem', href: '/admin/system-logs', icon: FileText },
                    { title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText },
                ]
              : [];
        if (sistemItems.length > 0) {
            sections.push({ label: 'Sistem', items: sistemItems });
        }
    }

    if (hasFinanceAccess) {
        sections.push({ label: 'Keuangan', items: financeItems(auth, isSuperAdmin) });
    }

    if (canAccessKitabGrades && !isAdmin) {
        sections.push({
            label: 'Guru',
            items: [
                ...(hasGuruRecord
                    ? [
                          { title: 'Jadwal Guru', href: '/admin/schedule', icon: CalendarDays },
                          { title: 'Absensi Siswa', href: '/admin/attendance-sessions', icon: CalendarClock },
                          { title: 'Nilai Kitab', href: '/admin/kitab-grades', icon: ClipboardList },
                      ]
                    : []),
                ...(hasKitabReadingExaminerRecord
                    ? [{ title: 'Nilai Baca Kitab', href: '/admin/kitab-reading-assessments', icon: BookOpenCheck }]
                    : []),
            ],
        });
    }

    if (isMusyrif) {
        sections.push({ label: 'Musyrif', items: [...musyrifOperasionalNav] });
    }

    if (isSantri) {
        sections.push({ label: 'Akademik', items: [...santriNav] });
    }

    if (isWaliSantri) {
        sections.push({ label: 'Anak Saya', items: [...waliNav] });
    }

    if (canAccessRaportKelas) {
        sections.push({ label: 'Wali Kelas', items: [...waliKelasNav] });
    }

    return sections
        .map((section) => ({ ...section, items: section.items.filter(Boolean) }))
        .filter((section) => section.items.length > 0);
}
