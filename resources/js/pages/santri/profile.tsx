import { Head, Link } from '@inertiajs/react';
import { BookOpen, Calendar, Home, IdCard, PencilLine, Phone, Shield, User, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

/** Fonts & theme: see resources/css/app.css (Plus Jakarta Sans, Manhood tokens). */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Profil', href: '/santri/profile' },
];

type Props = { student: Student };

/* ─── Reusable Info Row ─────────────────────────────────────────── */
function InfoRow({
    icon: Icon,
    label,
    value,
    mono,
}: {
    icon?: React.ElementType;
    label: string;
    value: React.ReactNode;
    mono?: boolean;
}) {
    return (
        <div className="group flex flex-col gap-1 py-3.5 transition-colors sm:flex-row sm:items-center sm:gap-4">
            <div className="flex items-center gap-3 sm:w-48 sm:shrink-0">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:bg-emerald-50 group-hover:text-emerald-600">
                    {Icon ? <Icon size={16} strokeWidth={2} /> : <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />}
                </div>
                <span className=" text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    {label}
                </span>
            </div>
            <div className={` text-sm font-medium text-foreground sm:flex-1 ${mono ? 'font-mono tracking-wider' : ''}`}>
                {value ?? <span className="font-normal text-muted-foreground italic">Belum ada data</span>}
            </div>
        </div>
    );
}

/* ─── Section Card ──────────────────────────────────────────────── */
function SectionCard({
    icon: Icon,
    title,
    subtitle,
    action,
    children,
}: {
    icon: React.ElementType;
    title: string;
    subtitle?: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white border border-border shadow-sm transition-all hover:shadow-md">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/70 bg-muted/50 px-6 py-5">
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm border border-border text-foreground/90">
                        <Icon size={20} strokeWidth={1.5} />
                    </div>
                    <div>
                        <h3 className=" text-2xl font-bold leading-none text-foreground">
                            {title}
                        </h3>
                        {subtitle && (
                            <p className="mt-1  text-sm text-muted-foreground">{subtitle}</p>
                        )}
                    </div>
                </div>
                {action}
            </div>
            <div className="divide-y divide-border/70 px-6">{children}</div>
        </div>
    );
}

/* ─── Guardian Card ─────────────────────────────────────────────── */
function GuardianCard({
    guardian,
    index,
}: {
    guardian: NonNullable<Student['guardians']>[number];
    index: number;
}) {
    const initials = guardian.full_name
        ? guardian.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
        : '?';

    const relationship = guardian.relationship ?? guardian.pivot?.relationship;

    return (
        <div className="py-5 last:pb-5">
            {/* Guardian header */}
            <div className="mb-4 flex items-center gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sidebar  text-lg font-bold text-white shadow-sm">
                    {initials}
                </div>
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <p className=" text-xl font-bold leading-tight text-foreground">
                            {guardian.full_name}
                        </p>
                        <Badge variant="outline" className="bg-muted/40 text-xs  font-medium text-muted-foreground border-border">
                            Wali {index}
                        </Badge>
                    </div>
                    {relationship && (
                        <p className="mt-0.5  text-sm capitalize text-emerald-600 font-medium">
                            {relationship}
                        </p>
                    )}
                </div>
            </div>

            {/* Guardian detail rows */}
            <div className="grid gap-0 rounded-xl border border-border bg-white overflow-hidden sm:grid-cols-2">
                <div className="flex items-center gap-3 p-4 border-b border-border/70 sm:border-b-0 sm:border-r">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
                        <Phone size={16} strokeWidth={2} />
                    </div>
                    <div>
                        <p className=" text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            Telepon
                        </p>
                        <p className=" text-sm font-semibold text-foreground mt-0.5">
                            {guardian.phone ?? <span className="font-normal text-muted-foreground">—</span>}
                        </p>
                    </div>
                </div>
                
                <div className="flex items-center gap-3 p-4 border-b border-border/70 sm:border-b-0">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
                        <IdCard size={16} strokeWidth={2} />
                    </div>
                    <div>
                        <p className=" text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            NIK
                        </p>
                        <p className="font-mono text-sm font-medium tracking-wider text-foreground mt-0.5">
                            {guardian.nik ?? <span className="font-sans font-normal text-muted-foreground">—</span>}
                        </p>
                    </div>
                </div>

                {guardian.occupation && (
                    <div className="flex items-center gap-3 p-4 border-t border-border/70 sm:col-span-2 bg-muted/50">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-muted-foreground shadow-sm border border-border/70">
                            <Users size={16} strokeWidth={2} />
                        </div>
                        <div>
                            <p className=" text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                Pekerjaan
                            </p>
                            <p className=" text-sm font-medium text-foreground mt-0.5">
                                {guardian.occupation}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════════ */
/* Main Page                                                      */
/* ═══════════════════════════════════════════════════════════════ */
export default function SantriProfile({ student }: Props) {
    /* Initials for hero avatar */
    const initials = student.full_name
        ? student.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
        : '?';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Saya" />

            <div className="min-h-full bg-muted/50 ">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                    
                    {/* Header Title */}
                    <div className="mb-6">
                        <h1 className="text-3xl font-bold text-foreground  tracking-tight">
                            Profil Santri
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Kelola informasi pribadi dan data akademik Anda.
                        </p>
                    </div>

                    {/* ── Hero Banner ── */}
                    <div className="mb-8 relative overflow-hidden rounded-3xl bg-sidebar px-6 py-8 shadow-lg sm:px-10">
                        {/* Decorative background element */}
                        <div className="absolute -right-10 -top-24 h-64 w-64 rounded-full bg-sidebar/50 blur-3xl" />
                        <div className="absolute -left-10 -bottom-24 h-64 w-64 rounded-full bg-emerald-900/30 blur-3xl" />

                        <div className="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <div className="flex items-center gap-5 sm:gap-6">
                                {/* Avatar */}
                                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-white/10  text-3xl font-bold text-white shadow-inner backdrop-blur-md border border-white/20">
                                    {initials}
                                </div>
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-3">
                                        <h2 className=" italic text-3xl font-bold leading-tight text-white sm:text-4xl">
                                            {student.full_name}
                                        </h2>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary" className="bg-sidebar text-muted-foreground hover:bg-sidebar/80 border-none font-mono">
                                            NIS: {student.nis}
                                        </Badge>
                                        <Badge variant="secondary" className="bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border-none">
                                            {student.current_class?.name ?? 'Belum ada kelas'}
                                        </Badge>
                                        <Badge variant="default" className="bg-white text-foreground hover:bg-muted">
                                            {student.status}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            {/* Edit button */}
                            <Link
                                href="/santri/profile/edit"
                                className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white/10 px-5 py-3  text-sm font-semibold text-white backdrop-blur-md transition-all hover:bg-white/20 hover:scale-[1.02] active:scale-[0.98] border border-white/20"
                            >
                                <PencilLine size={16} />
                                Edit Profil
                            </Link>
                        </div>

                        {/* Stats strip */}
                        <div className="relative z-10 mt-8 grid grid-cols-2 gap-4 divide-x divide-white/10 border-t border-white/10 pt-6 sm:flex sm:gap-10 sm:divide-none">
                            <div>
                                <p className=" text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                    Tahun Masuk
                                </p>
                                <p className="mt-1  text-2xl font-bold text-white">
                                    {student.admission_year}
                                </p>
                            </div>
                            <div className="pl-4 sm:pl-0 border-l border-white/10 sm:border-l-0 sm:border-l sm:pl-10">
                                <p className=" text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                    Jenis Kelamin
                                </p>
                                <p className="mt-1  text-2xl font-bold text-white">
                                    {student.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                                </p>
                            </div>
                            {student.guardians && student.guardians.length > 0 && (
                                <div className="col-span-2 pt-4 sm:pt-0 border-t border-white/10 sm:border-t-0 sm:border-l sm:pl-10">
                                    <p className=" text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                        Data Wali
                                    </p>
                                    <p className="mt-1  text-2xl font-bold text-white">
                                        {student.guardians.length} Wali Terdaftar
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-col gap-6">
                        {/* ── Student Data Card ── */}
                        <SectionCard
                            icon={User}
                            title="Informasi Pribadi"
                            subtitle="Data dasar santri yang tercatat di sistem akademik"
                        >
                            <InfoRow icon={User} label="Nama Lengkap" value={student.full_name} />
                            <InfoRow icon={BookOpen} label="NIS" value={student.nis} mono />
                            <InfoRow icon={IdCard} label="NIK" value={student.nik} mono />
                            <InfoRow
                                icon={Users}
                                label="Jenis Kelamin"
                                value={student.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                            />
                            <InfoRow icon={Home} label="Tempat Lahir" value={student.birth_place} />
                            <InfoRow
                                icon={Calendar}
                                label="Tanggal Lahir"
                                value={
                                    student.birth_date
                                        ? new Date(student.birth_date).toLocaleDateString('id-ID', {
                                              day: 'numeric',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : null
                                }
                            />
                            <InfoRow icon={Home} label="Alamat" value={student.address} />
                            <InfoRow icon={BookOpen} label="Kelas" value={student.current_class?.name} />
                            
                            <div className="group flex flex-col gap-1 py-3.5 sm:flex-row sm:items-center sm:gap-4">
                                <div className="flex items-center gap-3 sm:w-48 sm:shrink-0">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                        <BookOpen size={16} strokeWidth={2} />
                                    </div>
                                    <span className=" text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Status
                                    </span>
                                </div>
                                <div className="sm:flex-1">
                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border-none shadow-none font-medium">
                                        {student.status}
                                    </Badge>
                                </div>
                            </div>
                        </SectionCard>

                        {/* ── Guardian Section ── */}
                        {student.guardians && student.guardians.length > 0 && (
                            <SectionCard
                                icon={Shield}
                                title="Informasi Wali"
                                subtitle="Orang tua atau wali santri yang bertanggung jawab"
                            >
                                {student.guardians.map((g, idx) => (
                                    <GuardianCard key={g.id} guardian={g} index={idx + 1} />
                                ))}
                            </SectionCard>
                        )}
                    </div>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}