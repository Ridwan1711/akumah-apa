import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import {
    AlertTriangle,
    ArrowRight,
    ClipboardList,
    GraduationCap,
    Home,
    ShieldAlert,
    UserCircle,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import {
    ManhoodDataTableShell,
    ManhoodPageHeader,
    ManhoodSectionCard,
    ManhoodStatCard,
} from '@/components/manhood';
import type { ManhoodStatTheme } from '@/components/manhood';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { canAny } from '@/lib/authz';
import type { Auth, BreadcrumbItem, DiniyyahScore, LeavePermission, SchoolClass, Student, StudentViolation, TeacherAssignment } from '@/types';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type AdminStats = { totalStudents: number; totalClasses: number; totalGuru: number; totalMusyrif: number };

type Props = {
    roleName?: string;
    stats?: AdminStats;
    recentViolations?: StudentViolation[];
    pendingLeaves?: LeavePermission[];
    classCounts?: (SchoolClass & { students_count: number })[];
    assignments?: TeacherAssignment[];
    student?: Student | null;
    recentGrades?: DiniyyahScore[];
    activeLeave?: LeavePermission | null;
    children?: Student[];
    assignedRoomId?: number | null;
    waliKelasClasses?: (SchoolClass & { students_count: number })[];
};

type ClassCountRow = SchoolClass & { students_count: number };

function ClassDistributionChart({ classCounts }: { classCounts: ClassCountRow[] }) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);

    const labels = useMemo(() => classCounts.map((c) => c.name), [classCounts]);
    const counts = useMemo(() => classCounts.map((c) => Number(c.students_count)), [classCounts]);

    useEffect(() => {
        if (!canvasRef.current || classCounts.length === 0) return;

        const chart = new Chart(canvasRef.current, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Santri',
                        data: counts,
                        backgroundColor: 'rgba(37, 99, 235, 0.55)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                const n = ctx.parsed.y;
                                return `${n} santri`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0 },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });

        return () => {
            chart.destroy();
        };
    }, [labels, counts, classCounts.length]);

    return (
        <div className="h-[min(320px,50vh)] w-full min-h-[240px]">
            <canvas ref={canvasRef} />
        </div>
    );
}

function AdminDashboard({ stats, recentViolations = [], pendingLeaves = [], classCounts = [] }: Props) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ManhoodStatCard title="Santri Aktif" value={stats?.totalStudents ?? 0} icon={Users} theme="blue" />
                <ManhoodStatCard title="Total Kelas" value={stats?.totalClasses ?? 0} icon={GraduationCap} theme="emerald" />
                <ManhoodStatCard title="Total Guru" value={stats?.totalGuru ?? 0} icon={ClipboardList} theme="violet" />
                <ManhoodStatCard title="Musyrif" value={stats?.totalMusyrif ?? 0} icon={ShieldAlert} theme="amber" />
            </div>

            <div className="mt-2 grid gap-6 lg:grid-cols-2">
                <ManhoodSectionCard
                    title="Pelanggaran Terbaru"
                    isEmpty={recentViolations.length === 0}
                    emptyText="Tidak ada pelanggaran terbaru."
                    emptyIcon={<AlertTriangle size={20} className="text-muted-foreground" />}
                    action={
                        <Link
                            href="/admin/violations"
                            className="group flex items-center text-xs font-semibold text-primary hover:text-primary/90"
                        >
                            Lihat Semua <ArrowRight size={14} className="ml-1 transition-transform group-hover:translate-x-1" />
                        </Link>
                    }
                >
                    <div className="flex flex-col gap-3">
                        {recentViolations.map((v) => (
                            <div
                                key={v.id}
                                className="flex items-center justify-between rounded-xl border border-border/80 bg-muted/30 p-3 transition-colors hover:bg-muted/50"
                            >
                                <div className="flex flex-col">
                                    <span className="text-sm font-bold text-foreground">{v.student?.full_name}</span>
                                    <span className="mt-0.5 text-xs text-muted-foreground">{v.violation_type?.name}</span>
                                </div>
                                <Badge
                                    variant="outline"
                                    className={`border-none font-semibold capitalize ${
                                        v.violation_type?.category === 'berat'
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                                            : v.violation_type?.category === 'sedang'
                                              ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                              : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {v.violation_type?.category}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </ManhoodSectionCard>

                <ManhoodSectionCard
                    title="Perizinan Menunggu"
                    isEmpty={pendingLeaves.length === 0}
                    emptyText="Tidak ada perizinan menunggu persetujuan."
                    emptyIcon={<AlertTriangle size={20} className="text-muted-foreground" />}
                    action={
                        <Link
                            href="/admin/leave-permissions"
                            className="group flex items-center text-xs font-semibold text-primary hover:text-primary/90"
                        >
                            Lihat Semua <ArrowRight size={14} className="ml-1 transition-transform group-hover:translate-x-1" />
                        </Link>
                    }
                >
                    <div className="flex flex-col gap-3">
                        {pendingLeaves.map((l) => (
                            <div
                                key={l.id}
                                className="flex flex-col justify-between gap-2 rounded-xl border border-amber-200/80 bg-amber-50/50 p-3 sm:flex-row sm:items-center dark:border-amber-900/40 dark:bg-amber-950/30"
                            >
                                <div className="flex flex-col">
                                    <span className="text-sm font-bold text-foreground">{l.student?.full_name}</span>
                                    <span className="mt-0.5 max-w-[250px] truncate text-xs text-muted-foreground">{l.reason}</span>
                                </div>
                                <Badge className="w-fit border-none bg-amber-100 font-medium text-amber-800 shadow-none hover:bg-amber-200 dark:bg-amber-950/50 dark:text-amber-200">
                                    Pending
                                </Badge>
                            </div>
                        ))}
                    </div>
                </ManhoodSectionCard>
            </div>

            {classCounts.length > 0 && (
                <div className="mt-2 rounded-xl border border-border bg-card p-5 shadow-sm dark:shadow-none">
                    <h3 className="mb-4 text-base font-bold text-foreground">Distribusi Santri per Kelas</h3>
                    <ClassDistributionChart classCounts={classCounts} />
                </div>
            )}
        </>
    );
}

function GuruDashboard({ assignments = [] }: Props) {
    return (
        <ManhoodSectionCard title="Kelas & Mata Pelajaran Diampu" isEmpty={assignments.length === 0} emptyText="Belum ada penugasan mengajar. Hubungi admin.">
            <ManhoodDataTableShell>
                <table className="w-full text-left text-sm whitespace-nowrap">
                    <thead className="border-b-2 border-border bg-muted/20 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide">Kelas</th>
                            <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide">Mata Pelajaran</th>
                            <th className="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/80">
                        {assignments.map((a) => (
                            <tr key={a.id} className="transition-colors hover:bg-muted/30">
                                <td className="px-4 py-3.5 font-medium text-foreground">{a.school_class?.name ?? '-'}</td>
                                <td className="px-4 py-3.5 text-muted-foreground">{a.subject?.name ?? '-'}</td>
                                <td className="px-4 py-3.5 text-right">
                                    <Link
                                        href={`/admin/kitab-grades/${a.period_id}/${a.subject_id}/${a.class_id}`}
                                        className="inline-flex items-center justify-center rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/15"
                                    >
                                        Input Nilai
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </ManhoodDataTableShell>
        </ManhoodSectionCard>
    );
}

function MusyrifDashboard({ recentViolations = [], pendingLeaves = [] }: Props) {
    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <ManhoodSectionCard title="Pelanggaran Ditangani" isEmpty={recentViolations.length === 0} emptyText="Tidak ada pelanggaran terbaru yang ditangani.">
                <div className="flex flex-col gap-3">
                    {recentViolations.map((v) => (
                        <div key={v.id} className="flex items-center justify-between rounded-xl border border-border/80 bg-muted/30 p-3">
                            <span className="text-sm font-bold text-foreground">{v.student?.full_name}</span>
                            <span className="rounded-md border border-border bg-card px-2 py-1 text-xs text-muted-foreground">
                                {v.violation_type?.name}
                            </span>
                        </div>
                    ))}
                </div>
            </ManhoodSectionCard>

            <ManhoodSectionCard title="Perizinan Menunggu" isEmpty={pendingLeaves.length === 0} emptyText="Tidak ada perizinan menunggu.">
                <div className="flex flex-col gap-3">
                    {pendingLeaves.map((l) => (
                        <div
                            key={l.id}
                            className="flex items-center justify-between rounded-xl border border-amber-200/80 bg-amber-50/40 p-3 dark:border-amber-900/40 dark:bg-amber-950/30"
                        >
                            <span className="text-sm font-bold text-foreground">{l.student?.full_name}</span>
                            <Badge className="border-none bg-amber-100 font-medium text-amber-800 shadow-none dark:bg-amber-950/50 dark:text-amber-200">
                                Pending
                            </Badge>
                        </div>
                    ))}
                </div>
            </ManhoodSectionCard>
        </div>
    );
}

function SantriDashboard({ student, recentGrades = [], activeLeave }: Props) {
    const statusTheme: ManhoodStatTheme = activeLeave ? 'amber' : 'slate';

    if (!student) {
        return (
            <div className="flex h-40 items-center justify-center rounded-xl border border-dashed border-border bg-card">
                <p className="text-muted-foreground">Data santri belum terhubung dengan akun Anda.</p>
            </div>
        );
    }

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ManhoodStatCard title="Kelas Saat Ini" value={student.current_class?.name ?? '-'} icon={GraduationCap} theme="blue" />
                <ManhoodStatCard title="Poin Pelanggaran" value={student.violation_summary?.total_points ?? 0} icon={AlertTriangle} theme="rose" />
                <ManhoodStatCard title="Status Izin" value={activeLeave ? 'Sedang Izin' : 'Aktif'} icon={Home} theme={statusTheme} />
            </div>

            <div className="mt-2">
                <ManhoodSectionCard title="Nilai akademik terbaru" isEmpty={recentGrades.length === 0} emptyText="Belum ada nilai yang dipublikasikan.">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {recentGrades.map((g) => (
                            <div key={g.id} className="flex items-center justify-between rounded-xl border border-border/80 bg-muted/30 p-4">
                                <span className="text-sm font-semibold text-foreground">{g.subject?.name}</span>
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl font-bold text-foreground">{g.score}</span>
                                    <Badge variant="outline" className="border-border bg-card font-bold shadow-sm">
                                        {g.grade_letter}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                </ManhoodSectionCard>
            </div>
        </>
    );
}

function WaliKelasDashboard({ waliKelasClasses = [] }: Props) {
    return (
        <ManhoodSectionCard title="Kelas yang Dibina (Wali Kelas)" isEmpty={waliKelasClasses.length === 0} emptyText="Anda belum ditugaskan sebagai wali kelas.">
            <div className="flex flex-col gap-4">
                {waliKelasClasses.map((c) => (
                    <div
                        key={c.id}
                        className="flex flex-col justify-between gap-4 rounded-xl border border-primary/25 bg-primary/5 p-5 sm:flex-row sm:items-center dark:border-primary/30 dark:bg-primary/10"
                    >
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                <Users size={20} />
                            </div>
                            <div>
                                <span className="text-2xl font-bold text-foreground">{c.name}</span>
                                <p className="mt-0.5 text-sm text-muted-foreground">{c.students_count} santri terdaftar</p>
                            </div>
                        </div>
                        <Link
                            href={`/wali-kelas/report-cards?class_id=${c.id}`}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-sidebar px-5 py-2.5 text-sm font-semibold text-sidebar-primary-foreground transition-all hover:bg-sidebar/90 active:scale-[0.98]"
                        >
                            Kelola Raport <ArrowRight size={16} />
                        </Link>
                    </div>
                ))}
            </div>
        </ManhoodSectionCard>
    );
}

function WaliDashboard({ children = [] }: Props) {
    if (children.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-12">
                <UserCircle size={48} className="mb-3 text-muted-foreground/50" />
                <p className="text-muted-foreground">Data santri (anak) belum terhubung ke akun Anda.</p>
            </div>
        );
    }

    return (
        <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            {children.map((child) => {
                const initials =
                    child.full_name
                        ?.split(' ')
                        .slice(0, 2)
                        .map((w) => w[0])
                        .join('')
                        .toUpperCase() || '?';

                return (
                    <div
                        key={child.id}
                        className="flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-all hover:shadow-md dark:shadow-none"
                    >
                        <div className="relative overflow-hidden bg-sidebar p-5 text-center">
                            <div className="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-white/10 blur-xl" />
                            <div className="absolute -bottom-8 -left-8 h-24 w-24 rounded-full bg-primary/20 blur-xl" />

                            <div className="relative z-10 flex flex-col items-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-card font-brand text-2xl font-bold text-foreground shadow-md">
                                    {initials}
                                </div>
                                <h3 className="mt-3 text-2xl font-bold text-sidebar-primary-foreground">{child.full_name}</h3>
                                <p className="mt-1 text-xs text-sidebar-foreground">
                                    NIS: {child.nis} • {child.current_class?.name ?? 'Belum ada kelas'}
                                </p>
                            </div>
                        </div>

                        <div className="flex-1 p-5">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex flex-col items-center justify-center rounded-xl border border-primary/20 bg-primary/5 p-3 text-center">
                                    <p className="text-sm font-bold text-primary">{child.current_class?.name ?? '-'}</p>
                                    <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-primary/80">Kelas Aktif</p>
                                </div>
                                <div className="flex flex-col items-center justify-center rounded-xl border border-destructive/25 bg-destructive/5 p-3 text-center">
                                    <p className="text-3xl font-bold text-destructive">{child.violation_summary?.total_points ?? 0}</p>
                                    <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-destructive/80">Poin Pelanggaran</p>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-border bg-muted/20 p-4">
                            <Link
                                href={`/wali/children/${child.id}`}
                                className="flex w-full items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold text-foreground shadow-sm transition-all hover:border-primary/30 hover:bg-muted/50 hover:text-primary active:scale-[0.98]"
                            >
                                Lihat Detail Akademik <ArrowRight size={16} />
                            </Link>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function Dashboard(props: Props) {
    const { roleName } = props;
    const { auth } = usePage<{ auth?: Auth }>().props;
    const isAdmin = canAny(auth, ['dashboard.admin.view', 'invoice.view', 'payment.view'])
        || !!(roleName && ['super_admin', 'admin_akademik', 'admin_keuangan'].includes(roleName));
    const isGuru = canAny(auth, ['dashboard.guru.view']) || (props.assignments?.length ?? 0) > 0;
    const isWali = canAny(auth, ['dashboard.wali.view']) || roleName === 'wali_santri';
    const isSantri = canAny(auth, ['dashboard.santri.view']) || roleName === 'santri';

    const getDashboardTitle = () => {
        if (isAdmin) return 'Dashboard Admin';
        if (isGuru) return 'Dashboard Guru';
        if (props.waliKelasClasses?.length) return 'Dashboard Wali Kelas';
        if (roleName === 'musyrif') return 'Dashboard Musyrif';
        if (isSantri) return 'Dashboard Santri';
        if (isWali) return 'Dashboard Wali Santri';
        return 'Dashboard';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl">
                <ManhoodPageHeader
                    title={getDashboardTitle()}
                    description="Selamat datang! Berikut adalah ringkasan informasi dan aktivitas terbaru Anda."
                />

                <div className="flex flex-col gap-6">
                    {isAdmin && <AdminDashboard {...props} />}
                    {isGuru && <GuruDashboard {...props} />}
                    {(props.waliKelasClasses?.length ?? 0) > 0 && <WaliKelasDashboard {...props} />}
                    {roleName === 'musyrif' && <MusyrifDashboard {...props} />}
                    {isSantri && <SantriDashboard {...props} />}
                    {isWali && <WaliDashboard {...props} />}
                </div>

                <div className="h-10" />
            </div>
        </AppLayout>
    );
}
