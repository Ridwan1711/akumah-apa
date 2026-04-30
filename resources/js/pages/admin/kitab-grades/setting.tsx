import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, BookOpenCheck, Columns3, GraduationCap } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudPageHeader,
    CrudStatStrip,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { AssessmentComponent, BreadcrumbItem, SchoolClass, Subject } from '@/types';

type Props = {
    academicPeriod: { id: number; name: string };
    subject: Pick<Subject, 'id' | 'name'>;
    schoolClass: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>;
    assessmentComponents: Pick<AssessmentComponent, 'id' | 'name' | 'type' | 'is_core_required'>[];
    defaultActiveComponentIds: number[];
    isComponentLocked: boolean;
    inputUrl: string;
};

export default function KitabGradeSetting({
    academicPeriod,
    subject,
    schoolClass,
    assessmentComponents,
    defaultActiveComponentIds,
    isComponentLocked,
    inputUrl,
}: Props) {
    const subjectUrl = `/admin/kitab-grades/${academicPeriod.id}`;
    const classUrl = `/admin/kitab-grades/${academicPeriod.id}/${subject.id}`;
    const settingUrl = `/admin/kitab-grades/${academicPeriod.id}/${subject.id}/${schoolClass.id}/setting`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Nilai Diniyyah', href: '/admin/kitab-grades' },
        { title: academicPeriod.name, href: subjectUrl },
        { title: subject.name, href: classUrl },
        { title: schoolClass.name, href: settingUrl },
    ];

    const { data, setData, processing, post } = useForm({
        active_component_ids: defaultActiveComponentIds,
    });

    function toggleComponent(componentId: number, isCoreRequired: boolean) {
        if (isComponentLocked || isCoreRequired) {
            return;
        }

        const alreadyActive = data.active_component_ids.includes(componentId);
        if (alreadyActive) {
            setData(
                'active_component_ids',
                data.active_component_ids.filter((id) => id !== componentId),
            );
            return;
        }

        setData('active_component_ids', [...data.active_component_ids, componentId]);
    }

    function submitSetting(e: React.FormEvent) {
        e.preventDefault();
        post(settingUrl);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Setting Komponen — ${subject.name}`} />
            <div>
                <CrudPageHeader
                    title="Tentukan Komponen Penilaian"
                    description="Komponen yang dipilih akan dikunci untuk mapel-kelas ini selama 1 semester."
                />

                <CrudStatStrip
                    items={[
                        { key: 'components', label: 'Komponen tersedia', value: assessmentComponents.length, icon: <Columns3 size={18} />, tone: 'green' },
                        { key: 'subject', label: 'Pelajaran', value: subject.name, icon: <BookOpenCheck size={18} />, tone: 'blue' },
                        { key: 'class', label: 'Kelas', value: schoolClass.name, icon: <GraduationCap size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudCard title="Setting Asesmen Sesi Semester" subtitle="Pastikan pilihan sesuai kebutuhan sebelum melanjutkan ke input nilai.">
                    <div className="mcr-card-alert warning">
                        <AlertTriangle size={16} />
                        <span>
                            Bagian Mana saja yang akan dinilai?.
                            <br />
                            Setelah disimpan, komponen tidak dapat diubah lagi sampai semester ini berakhir.
                        </span>
                    </div>

                    <form onSubmit={submitSetting} style={{ marginTop: 12 }}>
                        <div style={{ display: 'grid', gap: 8 }}>
                            {assessmentComponents.map((component) => {
                                const checked = data.active_component_ids.includes(component.id);
                                const disabled = isComponentLocked || !!component.is_core_required;

                                return (
                                    <label
                                        key={component.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            border: '1px solid var(--border)',
                                            borderRadius: 8,
                                            padding: '10px 12px',
                                            cursor: disabled ? 'not-allowed' : 'pointer',
                                            opacity: disabled ? 0.9 : 1,
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            disabled={disabled}
                                            onChange={() => toggleComponent(component.id, !!component.is_core_required)}
                                        />
                                        <span style={{ fontWeight: 600 }}>
                                            {component.name}
                                        </span>
                                        <span className="mcr-table-meta">
                                            {component.type === 'daily' ? 'Harian' : 'Ujian'}
                                            {component.is_core_required ? ' • inti (wajib)' : ''}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <Link href={classUrl} className="mcr-btn ghost">Kembali</Link>
                            <Link href={inputUrl} className="mcr-btn secondary">Ke Input Nilai</Link>
                            <button type="submit" className="mcr-btn primary" disabled={processing || isComponentLocked}>
                                {isComponentLocked ? 'Sudah Dikunci' : (processing ? 'Menyimpan...' : 'Simpan & Kunci Komponen')}
                            </button>
                        </div>
                    </form>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
