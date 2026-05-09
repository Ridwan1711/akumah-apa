import { Head, router } from '@inertiajs/react';
import { Layers3, Link2, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import { AppMultiSelect, CrudCard, CrudPageHeader, CrudStatStrip } from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, GradeLevel, Subject } from '@/types';

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

    const initialLevelIds = useMemo(
        () => Array.from(new Set(gradeSubjects.map((item) => String(item.grade_level_id)))),
        [gradeSubjects],
    );
    const initialSubjectIds = useMemo(
        () => Array.from(new Set(gradeSubjects.map((item) => String(item.subject_id)))),
        [gradeSubjects],
    );

    const [selectedLevelIds, setSelectedLevelIds] = useState<string[]>(initialLevelIds);
    const [selectedSubjectIds, setSelectedSubjectIds] = useState<string[]>(initialSubjectIds);
    const [isSaving, setIsSaving] = useState(false);
    const [busyKey, setBusyKey] = useState<string | null>(null);

    const mappedPairSet = useMemo(
        () => new Set(gradeSubjects.map((item) => `${item.grade_level_id}:${item.subject_id}`)),
        [gradeSubjects],
    );

    const matchingPairCount = useMemo(() => {
        let count = 0;
        for (const levelId of selectedLevelIds) {
            for (const subjectId of selectedSubjectIds) {
                if (mappedPairSet.has(`${levelId}:${subjectId}`)) count += 1;
            }
        }
        return count;
    }, [selectedLevelIds, selectedSubjectIds, mappedPairSet]);

    function submitSync() {
        setIsSaving(true);
        router.post(
            '/admin/subject-level-mappings/sync',
            {
                level_ids: selectedLevelIds.map((id) => Number(id)),
                subject_ids: selectedSubjectIds.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    }

    function deleteSinglePair(levelId: number, subjectId: number, levelName: string, subjectName: string) {
        const key = `${levelId}:${subjectId}`;
        if (
            !window.confirm(
                `Hapus mapping "${subjectName}" dari tingkat "${levelName}"?\n\n` +
                    'Default per-level dan override per-kelas yang menempel akan ikut dihapus. ' +
                    'Penugasan guru terkait tidak dihapus otomatis.',
            )
        ) {
            return;
        }
        setBusyKey(key);
        router.delete('/admin/subject-level-mappings/pair', {
            data: { level_id: levelId, subject_id: subjectId },
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
        });
    }

    function bulkDelete() {
        if (selectedLevelIds.length === 0 || selectedSubjectIds.length === 0) {
            window.alert('Pilih minimal 1 tingkat dan 1 mapel terlebih dahulu.');
            return;
        }
        if (matchingPairCount === 0) {
            window.alert('Tidak ada pasangan terpasang yang cocok dengan seleksi.');
            return;
        }
        if (
            !window.confirm(
                `Hapus ${matchingPairCount} pasangan mapel-tingkat (cross-product dari seleksi)?\n\n` +
                    'Default per-level dan override per-kelas terkait akan ikut dihapus.',
            )
        ) {
            return;
        }
        setIsSaving(true);
        router.post(
            '/admin/subject-level-mappings/bulk-detach',
            {
                level_ids: selectedLevelIds.map((id) => Number(id)),
                subject_ids: selectedSubjectIds.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mapping Mapel-Tingkat" />
            <div>
                <CrudPageHeader
                    title="Mapping Mapel ke Tingkat"
                    description="Pilih banyak tingkat dan mapel sekaligus. Tombol Tambah akan membuat pasangan baru, sedangkan tombol Hapus Mapping akan melepas pasangan yang sudah cocok dengan seleksi."
                />
                <CrudStatStrip
                    items={[
                        {
                            key: 'levels',
                            label: 'Level Dipilih',
                            value: selectedLevelIds.length,
                            icon: <Layers3 size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'subjects',
                            label: 'Mapel Dipilih',
                            value: selectedSubjectIds.length,
                            icon: <Link2 size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'pairs',
                            label: 'Pasangan Terpasang (match)',
                            value: matchingPairCount,
                            icon: <Trash2 size={18} />,
                            tone: 'amber',
                        },
                    ]}
                />
                <FlashMessage />
                <CrudCard
                    title="Bulk Mapping"
                    subtitle="Gunakan multi-select untuk menentukan pasangan level dan mapel."
                >
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label>Tingkat (multi-select)</label>
                            <AppMultiSelect
                                options={levelOptions}
                                value={levelOptions.filter((opt) =>
                                    selectedLevelIds.includes(String(opt.value)),
                                )}
                                onChange={(items) =>
                                    setSelectedLevelIds((items ?? []).map((item) => String(item.value)))
                                }
                                isDisabled={isSaving}
                                placeholder="Pilih tingkat..."
                            />
                        </div>
                        <div className="mcr-form-group full">
                            <label>Pelajaran (multi-select)</label>
                            <AppMultiSelect
                                options={subjectOptions}
                                value={subjectOptions.filter((opt) =>
                                    selectedSubjectIds.includes(String(opt.value)),
                                )}
                                onChange={(items) =>
                                    setSelectedSubjectIds((items ?? []).map((item) => String(item.value)))
                                }
                                isDisabled={isSaving}
                                placeholder="Pilih pelajaran..."
                            />
                        </div>
                        <div className="mcr-action-group" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <button
                                type="button"
                                className="mcr-btn primary"
                                onClick={submitSync}
                                disabled={isSaving}
                            >
                                {isSaving ? 'Memproses...' : 'Tambah Mapping'}
                            </button>
                            <button
                                type="button"
                                className="mcr-btn danger"
                                onClick={bulkDelete}
                                disabled={isSaving || matchingPairCount === 0}
                                title={
                                    matchingPairCount === 0
                                        ? 'Tidak ada pasangan terpasang yang cocok dengan seleksi'
                                        : `Hapus ${matchingPairCount} pasangan terpasang yang cocok`
                                }
                            >
                                <Trash2 size={14} style={{ marginRight: 4 }} />
                                Hapus Mapping{matchingPairCount > 0 ? ` (${matchingPairCount})` : ''}
                            </button>
                        </div>
                    </div>
                </CrudCard>

                <CrudCard
                    title="Tingkat dan Mapel Terpasang"
                    subtitle="Klik tombol × pada tiap mapel untuk menghapus pasangan tunggal."
                >
                    <div className="mcr-table-wrap">
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 200 }}>Tingkat</th>
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
                                                    <div
                                                        style={{
                                                            display: 'flex',
                                                            flexWrap: 'wrap',
                                                            gap: 6,
                                                        }}
                                                    >
                                                        {mappedSubjects.map((subject) => {
                                                            const key = `${level.id}:${subject.id}`;
                                                            const isBusy = busyKey === key;
                                                            return (
                                                                <span
                                                                    key={key}
                                                                    className="mcr-chip"
                                                                    style={{
                                                                        display: 'inline-flex',
                                                                        alignItems: 'center',
                                                                        gap: 4,
                                                                        padding: '2px 4px 2px 10px',
                                                                        borderRadius: 999,
                                                                        border: '1px solid var(--border, #e5e7eb)',
                                                                        background: 'var(--muted, #f3f4f6)',
                                                                        fontSize: 12,
                                                                        opacity: isBusy ? 0.5 : 1,
                                                                    }}
                                                                >
                                                                    {subject.name}
                                                                    <button
                                                                        type="button"
                                                                        aria-label={`Hapus ${subject.name} dari ${level.name}`}
                                                                        title={`Hapus ${subject.name} dari ${level.name}`}
                                                                        onClick={() =>
                                                                            deleteSinglePair(
                                                                                level.id,
                                                                                subject.id,
                                                                                level.name,
                                                                                subject.name,
                                                                            )
                                                                        }
                                                                        disabled={isBusy || isSaving}
                                                                        style={{
                                                                            display: 'inline-flex',
                                                                            alignItems: 'center',
                                                                            justifyContent: 'center',
                                                                            width: 18,
                                                                            height: 18,
                                                                            borderRadius: 999,
                                                                            border: 'none',
                                                                            background: 'transparent',
                                                                            cursor: isBusy ? 'wait' : 'pointer',
                                                                            color: '#dc2626',
                                                                        }}
                                                                    >
                                                                        <X size={12} />
                                                                    </button>
                                                                </span>
                                                            );
                                                        })}
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
