import { Link } from '@inertiajs/react';
import { PencilLine } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { Student } from '@/types';

const statusMap: Record<string, { label: string; color: string }> = {
    active:  { label: 'Aktif',   color: 'bg-emerald-500/20 text-emerald-300 border-none' },
    alumni:  { label: 'Alumni',  color: 'bg-sky-500/20 text-sky-300 border-none' },
    keluar:  { label: 'Keluar',  color: 'bg-orange-500/20 text-orange-300 border-none' },
    wafat:   { label: 'Wafat',   color: 'bg-red-500/20 text-red-300 border-none' },
};

type Stat = { label: string; value: React.ReactNode };

type Props = {
    student: Pick<
        Student,
        'full_name' | 'nis' | 'gender' | 'status' | 'admission_year' | 'current_class' | 'guardians'
    >;
    editHref?: string;
    extraStats?: Stat[];
    /** Tambahan action custom di kanan hero */
    actions?: React.ReactNode;
};

export function ProfileHero({ student, editHref, extraStats = [], actions }: Props) {
    const initials = student.full_name
        ? student.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
        : '?';

    const statusStyle = statusMap[student.status];

    const defaultStats: Stat[] = [
        { label: 'Tahun Masuk', value: student.admission_year },
        { label: 'Jenis Kelamin', value: student.gender === 'L' ? 'Laki-laki' : 'Perempuan' },
        ...(student.guardians && student.guardians.length > 0
            ? [{ label: 'Data Wali', value: `${student.guardians.length} Wali` }]
            : []),
        ...extraStats,
    ];

    return (
        <div className="relative overflow-hidden rounded-3xl bg-sidebar px-6 py-8 shadow-lg sm:px-10">
            <div className="absolute -right-10 -top-24 h-64 w-64 rounded-full bg-sidebar/50 blur-3xl" />
            <div className="absolute -left-10 -bottom-24 h-64 w-64 rounded-full bg-emerald-900/30 blur-3xl" />

            <div className="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-5 sm:gap-6">
                    <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-3xl font-bold text-white shadow-inner backdrop-blur-md border border-white/20">
                        {initials}
                    </div>
                    <div className="flex flex-col">
                        <h2 className="italic text-3xl font-bold leading-tight text-white sm:text-4xl">
                            {student.full_name}
                        </h2>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" className="bg-sidebar text-muted-foreground border-none font-mono hover:bg-sidebar/80">
                                NIS: {student.nis}
                            </Badge>
                            <Badge variant="secondary" className="bg-emerald-500/20 text-emerald-300 border-none hover:bg-emerald-500/30">
                                {student.current_class?.name ?? 'Belum ada kelas'}
                            </Badge>
                            <Badge variant="secondary" className={statusStyle?.color ?? 'bg-white/20 text-white border-none'}>
                                {statusStyle?.label ?? student.status}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                    {editHref && (
                        <Link
                            href={editHref}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur-md transition-all hover:bg-white/20 hover:scale-[1.02] active:scale-[0.98] border border-white/20"
                        >
                            <PencilLine size={16} />
                            Edit Profil
                        </Link>
                    )}
                </div>
            </div>

            <div className="relative z-10 mt-8 flex flex-wrap gap-8 border-t border-white/10 pt-6">
                {defaultStats.map((stat, i) => (
                    <div key={i}>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                            {stat.label}
                        </p>
                        <p className="mt-1 text-xl font-bold text-white">{stat.value}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}
