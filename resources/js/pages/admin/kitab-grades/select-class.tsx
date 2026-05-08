import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { CrudCard, CrudPageHeader } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, Subject } from '@/types';

type Props = {
    academicPeriod: { id: number; name: string };
    subject: Pick<Subject, 'id' | 'name'>;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
};

export default function KitabGradesSelectClass({
    academicPeriod,
    subject,
    classes,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Nilai Diniyyah', href: '/admin/kitab-grades' },
        { title: academicPeriod.name, href: `/admin/kitab-grades/${academicPeriod.id}` },
        { title: subject.name, href: `/admin/kitab-grades/${academicPeriod.id}/${subject.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pilih Kelas - ${subject.name}`} />
            <div>
                <CrudPageHeader
                    title="Pilih Kelas"
                    description={`Mapel ${subject.name} · Periode ${academicPeriod.name}`}
                />

                <CrudCard title="Daftar Kelas Tersedia">
                    {classes.length === 0 ? (
                        <div className="mcr-empty-state">
                            <Users size={28} />
                            <h3>Tidak ada kelas tersedia</h3>
                            <p>Belum ada kelas dengan penilaian aktif untuk mapel ini. atau Mapel ini Tidak Diperbolehkan Input Nilai</p>
                        </div>
                    ) : (
                        <div style={{ display: 'grid', gap: 10 }}>
                            {classes.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/admin/kitab-grades/${academicPeriod.id}/${subject.id}/${item.id}`}
                                    className="mcr-btn secondary"
                                    style={{ justifyContent: 'space-between', height: 44, textDecoration: 'none' }}
                                >
                                    <span style={{ fontWeight: 600 }}>{item.name}</span>
                                    <span className="mcr-table-meta">Jenjang #{item.grade_level_id ?? '-'}</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </CrudCard>
            </div>
        </AppLayout>
    );
}
