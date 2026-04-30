import { Head, router } from '@inertiajs/react';
import { Layers3, Link2 } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import { AppMultiSelect, CrudCard, CrudPageHeader, CrudStatStrip } from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, GradeLevel, Subject } from '@/types';
import { useMemo, useState } from 'react';

type Props = {
    subjects: Array<Pick<Subject, 'id' | 'name'>>;
    levels: Pick<GradeLevel, 'id' | 'name' | 'order'>[];
    gradeSubjects: Array<{ id: number; grade_level_id: number; subject_id: number }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mapping Mapel-Tingkat', href: '/admin/subject-level-mappings' },
];

export default function SubjectLevelMappingsIndex({ subjects, levels, gradeSubjects }: Props) {
    const levelOptions = useMemo<SelectOption[]>(
        () => levels.map((level) => ({ value: String(level.id), label: level.name })),
        [levels],
    );
    const subjectOptions = useMemo<SelectOption[]>(
        () => subjects.map((subject) => ({ value: String(subject.id), label: subject.name })),
        [subjects],
    );

    const initialLevelIds = useMemo(() => Array.from(new Set(gradeSubjects.map((item) => String(item.grade_level_id)))), [gradeSubjects]);
    const initialSubjectIds = useMemo(() => Array.from(new Set(gradeSubjects.map((item) => String(item.subject_id)))), [gradeSubjects]);

    const [selectedLevelIds, setSelectedLevelIds] = useState<string[]>(initialLevelIds);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState<string[]>(initialSubjectIds);
    const [isSaving, setIsSaving] = useState(false);
    const mappedPairSet = useMemo(
        () => new Set(gradeSubjects.map((item) => `${item.grade_level_id}:${item.subject_id}`)),
        [gradeSubjects],
    );

    function submitSync() {
        setIsSaving(true);
        router.post('/admin/subject-level-mappings/sync', {
            level_ids: selectedLevelIds.map((id) => Number(id)),
            subject_ids: selectedSubjectIds.map((id) => Number(id)),
        }, {
            preserveScroll: true,
            onFinish: () => setIsSaving(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mapping Mapel-Tingkat" />
            <div>
                <CrudPageHeader
                    title="Mapping Mapel ke Tingkat"
                    description="Pilih banyak tingkat dan banyak mapel sekaligus. Kombinasi yang dipilih akan terhubung, yang tidak dipilih tidak dihubungkan."
                />
                <CrudStatStrip
                    items={[
                        { key: 'levels', label: 'Level Dipilih', value: selectedLevelIds.length, icon: <Layers3 size={18} />, tone: 'green' },
                        { key: 'subjects', label: 'Mapel Dipilih', value: selectedSubjectIds.length, icon: <Link2 size={18} />, tone: 'blue' },
                    ]}
                />
                <FlashMessage />
                <CrudCard title="Bulk Mapping" subtitle="Gunakan multi-select untuk menentukan pasangan level dan mapel.">
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label>Tingkat (multi-select)</label>
                            <AppMultiSelect
                                options={levelOptions}
                                value={levelOptions.filter((opt) => selectedLevelIds.includes(String(opt.value)))}
                                onChange={(items) => setSelectedLevelIds((items ?? []).map((item) => String(item.value)))}
                                isDisabled={isSaving}
                                placeholder="Pilih tingkat..."
                            />
                        </div>
                        <div className="mcr-form-group full">
                            <label>Pelajaran (multi-select)</label>
                            <AppMultiSelect
                                options={subjectOptions}
                                value={subjectOptions.filter((opt) => selectedSubjectIds.includes(String(opt.value)))}
                                onChange={(items) => setSelectedSubjectIds((items ?? []).map((item) => String(item.value)))}
                                isDisabled={isSaving}
                                placeholder="Pilih pelajaran..."
                            />
                        </div>
                        <div className="mcr-action-group">
                            <button type="button" className="mcr-btn primary" onClick={submitSync} disabled={isSaving}>
                                {isSaving ? 'Menyimpan...' : 'Simpan Mapping'}
                            </button>
                        </div>
                    </div>
                </CrudCard>

                <CrudCard title="Tingkat dan Mapel Terpasang">
                    <div className="mcr-table-wrap">
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Tingkat</th>
                                    <th>Mapel Terpasang</th>
                                </tr>
                            </thead>
                            <tbody>
                                {levels.map((level) => {
                                    const mappedSubjects = subjects.filter((subject) =>
                                        mappedPairSet.has(`${level.id}:${subject.id}`),
                                    );
                                    return (
                                        <tr key={level.id}>
                                            <td style={{ fontWeight: 600 }}>{level.name}</td>
                                            <td>
                                                {mappedSubjects.length > 0 ? (
                                                    <div className="mcr-form-group" style={{ gap: 6 }}>
                                                        {mappedSubjects.map((subject) => (
                                                            <div key={`${level.id}-${subject.id}`}>{subject.name}</div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
