import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CrudCard, CrudPageHeader, CrudToolbar } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Semester, Subject } from '@/types';

type Props = {
    academicPeriod: { id: number; name: string };
    semesters: (Pick<Semester, 'id' | 'name'> & { is_active?: boolean })[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    activeSemester?: Pick<Semester, 'id' | 'name'> | null;
};

export default function KitabGradesSelectSubject({
    academicPeriod,
    semesters,
    subjects,
    activeSemester,
}: Props) {
    const [q, setQ] = useState('');

    const filtered = useMemo(() => {
        const search = q.trim().toLowerCase();
        if (!search) return subjects;
        return subjects.filter((item) => item.name.toLowerCase().includes(search));
    }, [subjects, q]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Nilai Diniyyah', href: '/admin/kitab-grades' },
        { title: academicPeriod.name, href: `/admin/kitab-grades/${academicPeriod.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pilih Mata Pelajaran" />
            <div>
                <CrudPageHeader
                    title="Pilih Mata Pelajaran"
                    description={activeSemester ? `Semester aktif: ${activeSemester.name}` : `Periode: ${academicPeriod.name}`}
                />

                <CrudToolbar
                    left={(
                        <>
                            {semesters.length > 1 ? (
                                <select
                                    className="mcr-filter-select"
                                    value={String(academicPeriod.id)}
                                    onChange={(e) => router.get(`/admin/kitab-grades/${e.target.value}`)}
                                >
                                    {semesters.map((period) => (
                                        <option key={period.id} value={String(period.id)}>
                                            {period.name}{period.is_active ? ' · aktif' : ''}
                                        </option>
                                    ))}
                                </select>
                            ) : null}
                            <div className="mcr-search">
                                <Search size={14} />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Cari pelajaran..."
                                />
                            </div>
                        </>
                    )}
                />

                <CrudCard title="Daftar Pelajaran">
                    <div style={{ display: 'grid', gap: 10 }}>
                        {filtered.map((item) => (
                            <Link
                                key={item.id}
                                href={`/admin/kitab-grades/${academicPeriod.id}/${item.id}`}
                                className="mcr-btn secondary"
                                style={{ justifyContent: 'space-between', height: 44, textDecoration: 'none' }}
                            >
                                <span style={{ display: 'inline-flex', gap: 8, alignItems: 'center' }}>
                                    <BookOpen size={14} />
                                    {item.name}
                                </span>
                                <span className="mcr-table-meta">Pilih kelas</span>
                            </Link>
                        ))}
                    </div>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
