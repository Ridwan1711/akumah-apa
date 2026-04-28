import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpDown,
    Banknote,
    BookOpen,
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
    LayoutTemplate,
    ListChecks,
    PieChart,
    Receipt,
    ScrollText,
    Shield,
    Tag,
    User,
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
    const { auth, hasWaliKelasRecord = false, hasGuruRecord = false } = usePage<{ auth: Auth; hasWaliKelasRecord?: boolean; hasGuruRecord?: boolean }>().props;
    const roleName = auth.user.role?.name;
    const isAdmin = roleName && adminRoles.includes(roleName);
    const isKeuangan = (roleName && keuanganRoles.includes(roleName)) || canAny(auth, ['invoice.view', 'payment.view', 'payment.report.view']);
    const isSuperAdmin = roleName === 'super_admin';
    const isMusyrif = roleName && musyrifRoles.includes(roleName);
    const isSantri = roleName === 'santri';
    const isWaliSantri = roleName === 'wali_santri';
    const canAccessKitabGrades = canAny(auth, ['dashboard.guru.view', 'dashboard.admin.view']) || isAdmin || hasGuruRecord;
    const canAccessRaportKelas = hasWaliKelasRecord;

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    ];

    const dataNavItems: NavItem[] = [
        { title: 'Data Santri', href: '/admin/students', icon: Users },
        { title: 'Pengurus Santri', href: '/admin/student-positions', icon: Shield },
        { title: 'Enroll Kelas Santri', href: '/admin/student-enrollments', icon: UserPlus },
        { title: 'Kelas Diniyah', href: '/admin/diniyah-classes', icon: GraduationCap },
        { title: 'Tahun Ajaran', href: '/admin/academic-years', icon: CalendarDays },
        { title: 'Generate Akun', href: '/admin/account-generator', icon: KeyRound },
    ];

    const akademikNavItems: NavItem[] = [
        { title: 'Mata Pelajaran Kitab', href: '/admin/kitab-subjects', icon: BookOpen },
        { title: 'Komponen Penilaian', href: '/admin/assessment-components', icon: ListChecks },
        { title: 'Rule Penilaian Mapel', href: '/admin/class-subject-rules', icon: ClipboardList },
        { title: 'Penugasan Guru', href: '/admin/teaching-assignments', icon: UserPlus },
        { title: 'Jadwal Kitab', href: '/admin/schedules', icon: CalendarClock },
        { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets', icon: CalendarClock },
        { title: 'Kehadiran Santri', href: '/admin/attendances', icon: CalendarDays },
        { title: 'Nilai Kitab', href: '/admin/kitab-grades', icon: ClipboardList },
        { title: 'Raport', href: '/admin/report-cards', icon: ScrollText },
        { title: 'Template Raport', href: '/admin/report-card-templates', icon: LayoutTemplate },
        { title: 'Kenaikan Kelas', href: '/admin/class-promotion', icon: ArrowUpDown },
    ];

    const operasionalNavItems: NavItem[] = [
        { title: 'Asrama', href: '/admin/asrama', icon: Building },
        { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
        { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
    ];

    const keuanganNavItems: NavItem[] = [
        ...(canAny(auth, ['invoice.view']) ? [{ title: 'Tagihan', href: '/admin/invoices', icon: Banknote }] : []),
        ...(canAny(auth, ['payment.view']) ? [{ title: 'Pembayaran', href: '/admin/payments', icon: CreditCard }] : []),
        ...(canAny(auth, ['invoice.create']) ? [{ title: 'Diskon Santri', href: '/admin/student-discounts', icon: Wallet }] : []),
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
        { title: 'Jadwal Guru', href: '/admin/schedule', icon: CalendarDays },
        { title: 'Absensi Siswa', href: '/admin/attendance-sessions', icon: CalendarClock },
        { title: 'Nilai Kitab', href: '/admin/kitab-grades', icon: ClipboardList },
    ];

    const musyrifNavItems: NavItem[] = [
        { title: 'Pelanggaran', href: '/admin/violations', icon: AlertTriangle },
        { title: 'Perizinan Pulang', href: '/admin/leave-permissions', icon: Home },
    ];

    const santriNavItems: NavItem[] = [
        { title: 'Jadwal', href: '/santri/schedule', icon: CalendarDays },
        { title: 'Kehadiran', href: '/santri/attendances', icon: CalendarClock },
        { title: 'Nilai Kitab', href: '/santri/grades', icon: ClipboardList },
        { title: 'Pelanggaran', href: '/santri/violations', icon: AlertTriangle },
        { title: 'Profil', href: '/santri/profile', icon: User },
    ];

    const waliNavItems: NavItem[] = [
        { title: 'Data Anak', href: '/wali/children', icon: Users },
        { title: 'Tagihan', href: '/wali/invoices', icon: Banknote },
        { title: 'Riwayat Bayar', href: '/wali/payment-history', icon: Receipt },
    ];
    
    const waliKelasNavItems: NavItem[] = [
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
