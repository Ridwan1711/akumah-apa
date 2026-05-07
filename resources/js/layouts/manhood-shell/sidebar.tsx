import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpDown,
    Banknote,
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
    ListChecks,
    PieChart,
    Receipt,
    ScrollText,
    Shield,
    User,
    UserCheck,
    UserPlus,
    Users,
    Wallet
    
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { useMemo } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { can, canAny } from '@/lib/authz';
import { toUrl } from '@/lib/utils';
import type { Auth, NavItem } from '@/types';

type NavSection = {
    label: string;
    items: NavItem[];
};

const adminRoles = ['super_admin', 'admin_akademik'];
const keuanganRoles = ['super_admin', 'admin_keuangan'];
const musyrifRoles = ['musyrif'];

function hasAnyRole(userRoleNames: string[], allowedRoles: readonly string[]) {
    return allowedRoles.some((allowedRole) => userRoleNames.includes(allowedRole));
}

function getUserRoleNames(user: Auth['user']): string[] {
    const directRole = user.role?.name ? [user.role.name] : [];
    const relationRoles =
        'roles' in user && Array.isArray(user.roles)
            ? user.roles
                  .map((role) => role?.name)
                  .filter((roleName): roleName is string => !!roleName)
            : [];

    return Array.from(new Set([...directRole, ...relationRoles]));
}

function formatRoleLabel(roleNames: string[]): string {
    if (roleNames.length === 0) return 'User';

    return roleNames
        .map((roleName) => roleName.replace(/_/g, ' '))
        .join(', ');
}

function buildSections(
    auth: Auth,
    userRoleNames: string[],
    hasGuruRecord: boolean,
    hasWaliKelasRecord: boolean,
    hasKitabReadingExaminerRecord: boolean,
): NavSection[] {
    const isAdmin = hasAnyRole(userRoleNames, adminRoles);
    const hasFinanceAccess = canAny(auth, ['invoice.view', 'payment.view', 'payment.report.view']) || hasAnyRole(userRoleNames, keuanganRoles);
    const isSuperAdmin = userRoleNames.includes('super_admin');
    const isMusyrif = hasAnyRole(userRoleNames, musyrifRoles);
    const isSantri = userRoleNames.includes('santri');
    const isWaliSantri = userRoleNames.includes('wali_santri');
    const canAccessKitabGrades =
        canAny(auth, ['dashboard.guru.view', 'dashboard.admin.view']) || isAdmin || hasGuruRecord || hasKitabReadingExaminerRecord;
    const canAccessRaportKelas = hasWaliKelasRecord;

    const sections: NavSection[] = [
        {
            label: 'Utama',
            items: [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }],
        },
    ];

    if (isAdmin) {
        sections.push({
            label: 'Data Master',
            items: [
                { title: 'Data Santri', href: '/admin/students', icon: Users },
                { title: 'Wali Santri', href: '/admin/guardians', icon: UserCheck },
                { title: 'Pengurus Santri', href: '/admin/student-positions', icon: Shield },
                { title: 'Enroll Kelas Santri', href: '/admin/student-enrollments', icon: UserPlus },
                { title: 'Kelas Diniyah', href: '/admin/diniyah-classes', icon: GraduationCap },
                { title: 'Tahun Ajaran', href: '/admin/academic-years', icon: CalendarDays },
                { title: 'Generate Akun', href: '/admin/account-generator', icon: KeyRound },
            ],
        });
        sections.push({
            label: 'Akademik',
            items: [
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
            ],
        });
        sections.push({
            label: 'Operasional',
            items: [
                { title: 'Asrama', href: '/admin/asrama', icon: Building },
                { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
                { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
            ],
        });
        if (isSuperAdmin) {
            sections.push({
                label: 'Sistem',
                items: [
                    { title: 'Manajemen User', href: '/admin/users', icon: Shield },
                    { title: 'Log Sistem', href: '/admin/system-logs', icon: FileText },
                    { title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText },
                ],
            });
        } else if (can(auth, 'audit_log.view_akademik')) {
            sections.push({
                label: 'Sistem',
                items: [
                    { title: 'Log Sistem', href: '/admin/system-logs', icon: FileText },
                    { title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText },
                ],
            });
        }
    }

    if (hasFinanceAccess) {
        sections.push({
            label: 'Keuangan',
            items: [
                ...(canAny(auth, ['invoice.view']) ? [{ title: 'Tagihan', href: '/admin/invoices', icon: Banknote }] : []),
                ...(canAny(auth, ['payment.view']) ? [{ title: 'Pembayaran', href: '/admin/payments', icon: CreditCard }] : []),
                ...(canAny(auth, ['invoice.create']) ? [{ title: 'Diskon Santri', href: '/admin/student-discounts', icon: Wallet }] : []),
                ...(canAny(auth, ['payment.report.view']) ? [{ title: 'Laporan Keuangan', href: '/admin/payment-reports', icon: PieChart }] : []),
                ...(can(auth, 'audit_log.view_finance') && !isSuperAdmin
                    ? [{ title: 'Log Aktivitas Keuangan', href: '/admin/audit-logs', icon: FileText }]
                    : []),
            ],
        });
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
        sections.push({
            label: 'Musyrif',
            items: [
                { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
                { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
            ],
        });
    }

    if (isSantri) {
        sections.push({
            label: 'Akademik',
            items: [
                { title: 'Jadwal', href: '/santri/schedule', icon: CalendarDays },
                { title: 'Kehadiran', href: '/santri/attendances', icon: CalendarClock },
                { title: 'Nilai Kitab', href: '/santri/grades', icon: ClipboardList },
                { title: 'Pelanggaran', href: '/santri/violations', icon: AlertTriangle },
                { title: 'Profil', href: '/santri/profile', icon: User },
            ],
        });
    }

    if (isWaliSantri) {
        sections.push({
            label: 'Anak Saya',
            items: [
                { title: 'Data Anak', href: '/wali/children', icon: Users },
                { title: 'Tagihan', href: '/wali/invoices', icon: Banknote },
                { title: 'Riwayat Bayar', href: '/wali/payment-history', icon: Receipt },
            ],
        });
    }

    if (canAccessRaportKelas) {
        sections.push({
            label: 'Wali Kelas',
            items: [
                { title: 'Review Nilai Kelas', href: '/wali-kelas/grade-reviews', icon: ClipboardList },
                { title: 'Rekap Kenaikan Kelas', href: '/wali-kelas/class-promotion-recaps', icon: ArrowUpDown },
                { title: 'Raport Kelas', href: '/wali-kelas/report-cards', icon: ScrollText },
            ],
        });
    }

    return sections
        .map((section) => ({ ...section, items: section.items.filter(Boolean) }))
        .filter((section) => section.items.length > 0);
}

type Props = {
    collapsed: boolean;
    mobileOpen: boolean;
    onClose: () => void;
};

export function ShellSidebar({ collapsed, mobileOpen, onClose }: Props) {
    const { auth, hasGuruRecord = false, hasWaliKelasRecord = false, hasKitabReadingExaminerRecord = false } =
        usePage<{
            auth: Auth;
            hasGuruRecord?: boolean;
            hasWaliKelasRecord?: boolean;
            hasKitabReadingExaminerRecord?: boolean;
        }>().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();

    const user = auth.user;
    const initials = getInitials(user.name ?? '');
    const userRoleNames = useMemo(() => getUserRoleNames(user), [user]);
    const sections = useMemo(
        () => buildSections(auth, userRoleNames, !!hasGuruRecord, !!hasWaliKelasRecord, !!hasKitabReadingExaminerRecord),
        [auth, userRoleNames, hasGuruRecord, hasWaliKelasRecord, hasKitabReadingExaminerRecord],
    );
    const roleLabel = useMemo(() => formatRoleLabel(userRoleNames), [userRoleNames]);

    const sidebarClass = [
        'mhs-sidebar',
        collapsed ? 'mhs-collapsed' : '',
        mobileOpen ? 'mhs-mobile-open' : '',
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <aside className={sidebarClass} aria-label="Sidebar navigasi">
            <Link href="/dashboard" className="mhs-sidebar-logo" prefetch>
                <span className="mhs-logo-icon" aria-hidden="true">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                </span>
                <span className="mhs-logo-text">
                    <h2>Manhood Panel</h2>
                    <span>Pesantren Management</span>
                </span>
            </Link>

            <nav className="mhs-sidebar-nav" aria-label="Menu utama">
                {sections.map((section) => (
                    <div className="mhs-nav-section" key={section.label}>
                        <div className="mhs-nav-section-label">{section.label}</div>
                        {section.items.map((item) => {
                            const Icon = item.icon as LucideIcon | undefined;
                            const active = item.href === '/dashboard'
                                ? isCurrentUrl(item.href)
                                : isCurrentOrParentUrl(item.href);
                            return (
                                <Link
                                    key={`${section.label}-${item.title}`}
                                    href={item.href}
                                    prefetch
                                    onClick={onClose}
                                    className={`mhs-nav-item${active ? ' mhs-active' : ''}`}
                                    title={item.title}
                                >
                                    {Icon ? (
                                        <Icon size={18} strokeWidth={2} aria-hidden="true" />
                                    ) : (
                                        <span style={{ width: 18, height: 18, display: 'inline-block' }} />
                                    )}
                                    <span className="mhs-nav-item-label">{item.title}</span>
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="mhs-sidebar-footer">
                <Link href={toUrl('/settings/profile')} className="mhs-user-card" prefetch>
                    <span className="mhs-user-avatar" aria-hidden="true">
                        {initials || 'US'}
                    </span>
                    <span className="mhs-user-info">
                        <span className="mhs-name">{user.name}</span>
                        <span className="mhs-role">{roleLabel}</span>
                    </span>
                </Link>
            </div>
        </aside>
    );
}
