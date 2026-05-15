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
    Layers,
    LayoutGrid,
    ListChecks,
    LogOut,
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
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { can, canAny } from '@/lib/authz';
import type { Auth, NavItem } from '@/types';

const adminRoles = ['super_admin', 'admin_akademik'];
const keuanganRoles = ['super_admin', 'admin_keuangan'];
const musyrifRoles = ['musyrif'];

export function AppSidebar() {
    const {
        auth,
        hasWaliKelasRecord = false,
        hasGuruRecord = false,
        hasKitabReadingExaminerRecord = false,
    } = usePage<{
        auth: Auth;
        hasWaliKelasRecord?: boolean;
        hasGuruRecord?: boolean;
        hasKitabReadingExaminerRecord?: boolean;
    }>().props;
    const roleName = auth.user.role?.name;
    const isAdmin = roleName && adminRoles.includes(roleName);
    const isKeuangan = (roleName && keuanganRoles.includes(roleName)) || canAny(auth, ['invoice.view', 'payment.view', 'payment.report.view']);
    const isSuperAdmin = roleName === 'super_admin';
    const isMusyrif = roleName && musyrifRoles.includes(roleName);
    const isSantri = roleName === 'santri';
    const isWaliSantri = roleName === 'wali_santri';
    const canAccessKitabGrades = canAny(auth, ['dashboard.guru.view', 'dashboard.admin.view']) || isAdmin || hasGuruRecord || hasKitabReadingExaminerRecord;
    const canAccessRaportKelas = hasWaliKelasRecord;

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    ];

    const dataNavItems: NavItem[] = [
        { title: 'Tahun Ajaran', href: '/admin/academic-years', icon: CalendarDays },
        { title: 'Data Santri', href: '/admin/students', icon: Users },
        { title: 'Generate Akun', href: '/admin/account-generator', icon: KeyRound },
        { title: 'Pengurus Santri', href: '/admin/student-positions', icon: Shield },
        { title: 'Asrama & Kobong', href: '/admin/asrama', icon: Building },
        { title: 'Kelas Diniyah', href: '/admin/diniyah-classes', icon: GraduationCap },
        { title: 'Enroll Kelas Santri', href: '/admin/student-enrollments', icon: UserPlus },
        { title: 'Enroll Tingkat Formal', href: '/admin/formal-tingkat/assign', icon: School },
        { title: 'Master Tingkat Formal', href: '/admin/tingkat-sekolahs', icon: Layers },
    ];

    const akademikNavItems: NavItem[] = [
        { title: 'Mata Pelajaran', href: '/admin/kitab-subjects', icon: BookOpen },
        { title: 'Mapping Mapel-Tingkat', href: '/admin/subject-level-mappings', icon: ClipboardList },
        { title: 'Komponen Penilaian', href: '/admin/assessment-components', icon: ListChecks },
        { title: 'Aturan Mapel', href: '/admin/subject-settings', icon: ClipboardList },
        { title: 'Penugasan Guru', href: '/admin/teaching-assignments', icon: UserPlus },
        { title: 'Penguji Baca Kitab', href: '/admin/kitab-reading-examiners', icon: UserCheck },
        { title: 'Jadwal', href: '/admin/schedules', icon: CalendarClock },
        { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets', icon: CalendarClock },
        { title: 'Kehadiran Santri', href: '/admin/attendances', icon: CalendarDays },
        { title: 'Nilai Kitab', href: '/admin/kitab-grades', icon: ClipboardList },
        { title: 'Nilai Baca Kitab', href: '/admin/kitab-reading-assessments', icon: BookOpenCheck },
        { title: 'Raport', href: '/admin/report-cards', icon: ScrollText },
        { title: 'Kenaikan Kelas', href: '/admin/class-promotion', icon: ArrowUpDown },
    ];

    const operasionalNavItems: NavItem[] = [
        { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
        { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
    ];

    const keuanganNavItems: NavItem[] = [
        ...(canAny(auth, ['invoice.view']) ? [{ title: 'Tagihan', href: '/admin/invoices', icon: Banknote }] : []),
        ...(canAny(auth, ['payment.view']) ? [{ title: 'Pembayaran', href: '/admin/payments', icon: CreditCard }] : []),
        ...(canAny(auth, ['invoice.view', 'payment.view', 'payment.report.view'])
            ? [
                  { title: 'Diskon Santri', href: '/admin/student-discounts', icon: Wallet },
                  { title: 'Jenis Tagihan', href: '/admin/payment-types', icon: ClipboardList },
              ]
            : []),
        ...(canAny(auth, ['payment.report.view']) ? [{ title: 'Laporan Keuangan', href: '/admin/payment-reports', icon: PieChart }] : []),
        ...(can(auth, 'audit_log.view_finance') && !isSuperAdmin
            ? [{ title: 'Log Aktivitas Keuangan', href: '/admin/audit-logs', icon: FileText }]
            : []),
    ];

    const systemNavItems: NavItem[] = [];
    if (isSuperAdmin) {
        systemNavItems.push(
            { title: 'Manajemen User', href: '/admin/users', icon: Shield },
            { title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText },
        );
    } else if (can(auth, 'audit_log.view_akademik')) {
        systemNavItems.push({ title: 'Log Aktivitas', href: '/admin/audit-logs', icon: FileText });
    }

    const guruNavItems: NavItem[] = [
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
    ];

    const musyrifNavItems: NavItem[] = [
        { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
        { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
        { title: 'Keluar Pesantren', href: '/admin/student-withdrawals', icon: LogOut },
        { title: 'Lanjut MA10 / Kuliah', href: '/admin/formal-continuation', icon: GraduationCap },
    ];

    const santriNavItems: NavItem[] = [
        { title: 'Jadwal', href: '/santri/schedule', icon: CalendarDays },
        { title: 'Kehadiran', href: '/santri/attendances', icon: CalendarClock },
        { title: 'Nilai Kitab', href: '/santri/grades', icon: ClipboardList },
        { title: 'Pelanggaran', href: '/santri/violations', icon: AlertTriangle },
        { title: 'Profil', href: '/santri/profile', icon: User },
        { title: 'Keluar Pesantren', href: '/santri/withdrawal', icon: LogOut },
        { title: 'Lanjut Formal', href: '/santri/formal-continuation', icon: GraduationCap },
    ];

    const waliNavItems: NavItem[] = [
        { title: 'Data Anak', href: '/wali/children', icon: Users },
        { title: 'Tagihan', href: '/wali/invoices', icon: Banknote },
        { title: 'Riwayat Bayar', href: '/wali/payment-history', icon: Receipt },
    ];

    const waliKelasNavItems: NavItem[] = [
        { title: 'Review Nilai Kelas', href: '/wali-kelas/grade-reviews', icon: ClipboardList },
        { title: 'Rekap Kenaikan Kelas', href: '/wali-kelas/class-promotion-recaps', icon: ArrowUpDown },
        { title: 'Raport Kelas', href: '/wali-kelas/report-cards', icon: ScrollText },
    ];
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border px-1 py-2">
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center justify-between gap-2">
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <SidebarTrigger className="hidden md:inline-flex" />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Menu" />
                {isAdmin && (
                    <>
                        <NavMain items={dataNavItems} label="Data Master" collapsible />
                        <NavMain items={akademikNavItems} label="Akademik" collapsible />
                        <NavMain items={operasionalNavItems} label="Operasional" collapsible />
                        {systemNavItems.length > 0 && <NavMain items={systemNavItems} label="Sistem" collapsible />}
                    </>
                )}
                {isKeuangan && keuanganNavItems.length > 0 && <NavMain items={keuanganNavItems} label="Keuangan" collapsible />}
                {(canAccessKitabGrades) && <NavMain items={guruNavItems} label="Guru" collapsible />}
                {isMusyrif && <NavMain items={musyrifNavItems} label="Musyrif" collapsible />}
                {isSantri && <NavMain items={santriNavItems} label="Akademik" />}
                {isWaliSantri && <NavMain items={waliNavItems} label="Anak Saya" collapsible />}
                {(canAccessRaportKelas) && <NavMain items={waliKelasNavItems} label="Wali Kelas" collapsible />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
