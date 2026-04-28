import { Head, router } from '@inertiajs/react';
import { Layers3, ListChecks, ShieldAlert, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    AppMultiSelect,
    CrudCard,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, Semester, Subject } from '@/types';

type RuleRow = {
    id: number;
    class_id: number;
    subject_id: number;
    period_id: number;
    has_score: boolean;
    is_active: boolean;
};

type LevelDefaultRow = {
    id: number;
    level_id: number;
    subject_id: number;
    period_id: number;
    has_score_default: boolean;
    target_jam_default: number;
    is_mandatory_teaching: boolean;
};

type Props = {
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    subjects: Array<Pick<Subject, 'id' | 'name'>>;
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
    rules: RuleRow[];
    levelDefaults: LevelDefaultRow[];
    selectedLevelId: string;
    levelOptions: Array<{ value: string; label: string }>;
};

type ModeType = 'include' | 'exclude';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Rule Penilaian Mapel', href: '/admin/class-subject-rules' },
];

export default function ClassSubjectRulesIndex({
    classes,
    subjects,
    semesters,
    selectedPeriodId,
    selectedSemesterId,
    rules,
    levelDefaults,
    selectedLevelId,
    levelOptions,
}: Props) {
    const [semesterId, setSemesterId] = useState(String(selectedSemesterId));
    const [levelId, setLevelId] = useState(selectedLevelId);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<Pick<Subject, 'id' | 'name'> | null>(null);
    const [mode, setMode] = useState<ModeType>('include');
    const [selectedLevelIds, setSelectedLevelIds] = useState<string[]>([]);
    const [isSaving, setIsSaving] = useState(false);

    const classesByLevelId = useMemo(() => {
        const map = new Map<string, Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[]>();
        classes.forEach((schoolClass) => {
            const key = String(schoolClass.grade_level_id ?? '');
            const current = map.get(key) ?? [];
            current.push(schoolClass);
            map.set(key, current);
        });

        return map;
    }, [classes]);

    const multiLevelOptions = useMemo<SelectOption[]>(
        () =>
            Array.from(classesByLevelId.entries())
                .map(([id, cls]) => ({
                    value: id,
                    label: `${levelOptions.find((item) => item.value === id)?.label ?? `Level ${id}`} (${cls.length} kelas)`,
                }))
                .sort((a, b) => String(a.label).localeCompare(String(b.label), 'id-ID')),
        [classesByLevelId, levelOptions],
    );

    const defaultBySubject = useMemo(() => {
        const map = new Map<number, LevelDefaultRow>();
        levelDefaults
            .filter((item) => String(item.level_id) === levelId && item.period_id === selectedPeriodId)
            .forEach((item) => {
                map.set(item.subject_id, item);
            });

        return map;
    }, [levelDefaults, levelId, selectedPeriodId]);

    const subjectSummaryMap = useMemo(() => {
        const map = new Map<number, { wajibCount: number; tidakCount: number; unsetCount: number }>();
        const classIdsInLevel = classes
            .filter((schoolClass) => String(schoolClass.grade_level_id ?? '') === levelId)
            .map((item) => item.id);

        subjects.forEach((subject) => {
            let wajibCount = 0;
            let tidakCount = 0;

            classIdsInLevel.forEach((classId) => {
                const found = rules.find(
                    (rule) =>
                        rule.subject_id === subject.id &&
                        rule.class_id === classId &&
                        rule.period_id === selectedPeriodId,
                );

                if (!found) {
                    return;
                }

                if (found.has_score) {
                    wajibCount += 1;
                } else {
                    tidakCount += 1;
                }
            });

            map.set(subject.id, {
                wajibCount,
                tidakCount,
                unsetCount: classIdsInLevel.length - (wajibCount + tidakCount),
            });
        });

        return map;
    }, [subjects, classes, rules, selectedPeriodId, levelId]);

    const totalWajib = useMemo(
        () =>
            Array.from(subjectSummaryMap.values()).reduce(
                (carry, item) => carry + item.wajibCount,
                0,
            ),
        [subjectSummaryMap],
    );

    const totalTidak = useMemo(
        () =>
            Array.from(subjectSummaryMap.values()).reduce(
                (carry, item) => carry + item.tidakCount,
                0,
            ),
        [subjectSummaryMap],
    );

    const totalBelum = useMemo(
        () =>
            Array.from(subjectSummaryMap.values()).reduce(
                (carry, item) => carry + item.unsetCount,
                0,
            ),
        [subjectSummaryMap],
    );

    const selectedLookup = useMemo(() => new Set(selectedLevelIds), [selectedLevelIds]);

    const previewWajib = useMemo(() => {
        const inSelectedLevels = (item: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>) => {
            return selectedLookup.has(String(item.grade_level_id ?? ''));
        };

        if (mode === 'include') {
            return classes.filter((item) => inSelectedLevels(item));
        }

        return classes.filter((item) => !inSelectedLevels(item));
    }, [classes, mode, selectedLookup]);

    const previewTidak = useMemo(() => {
        const inSelectedLevels = (item: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>) => {
            return selectedLookup.has(String(item.grade_level_id ?? ''));
        };

        if (mode === 'include') {
            return classes.filter((item) => !inSelectedLevels(item));
        }

        return classes.filter((item) => inSelectedLevels(item));
    }, [classes, mode, selectedLookup]);

    function refreshByPeriod(nextSemesterId: string) {
        setIsRefreshing(true);
        router.get(
            '/admin/class-subject-rules',
            { semester_id: nextSemesterId, level_id: levelId },
            {
                preserveScroll: true,
                onFinish: () => setIsRefreshing(false),
            },
        );
    }

    function refreshByLevelId(nextLevelId: string) {
        setIsRefreshing(true);
        router.get(
            '/admin/class-subject-rules',
            { semester_id: semesterId, level_id: nextLevelId },
            {
                preserveScroll: true,
                onFinish: () => setIsRefreshing(false),
            },
        );
    }

    function openEditor(subject: Pick<Subject, 'id' | 'name'>) {
        const wajibIds = new Set(
            rules
            .filter(
                (rule) =>
                    rule.subject_id === subject.id &&
                    rule.period_id === selectedPeriodId &&
                    rule.has_score,
            )
            .map((rule) => rule.class_id),
        );

        const initialLevelIds: string[] = [];
        classesByLevelId.forEach((levelClasses, id) => {
            const allClassWajib = levelClasses.length > 0 && levelClasses.every((item) => wajibIds.has(item.id));
            if (allClassWajib) {
                initialLevelIds.push(id);
            }
        });

        setEditingSubject(subject);
        setMode('include');
        setSelectedLevelIds(initialLevelIds);
        setIsModalOpen(true);
    }

    function saveBulkRule() {
        if (!editingSubject) return;

        setIsSaving(true);
        router.post(
            '/admin/class-subject-rules/bulk',
            {
                subject_id: editingSubject.id,
                semester_id: Number(semesterId),
                mode,
                level_ids: selectedLevelIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsModalOpen(false),
                onFinish: () => setIsSaving(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rule Penilaian Mapel" />
            <div>
                <CrudPageHeader
                    title="Rule Penilaian Mata Pelajaran"
                    description="Klik pelajaran untuk atur rule per periode berdasarkan tag tingkat dengan mode Include/Exclude."
                />

                <CrudStatStrip
                    items={[
                        { key: 'subjects', label: 'Total Mapel', value: subjects.length, icon: <Layers3 size={18} />, tone: 'blue' },
                        { key: 'classes', label: `Kelas ${levelOptions.find((item) => item.value === levelId)?.label ?? levelId}`, value: classes.filter((item) => String(item.grade_level_id ?? '') === levelId).length, icon: <ListChecks size={18} />, tone: 'green' },
                        { key: 'wajib', label: 'Total Wajib Nilai', value: totalWajib, icon: <ShieldCheck size={18} />, tone: 'amber' },
                        { key: 'tidak', label: 'Total Tidak Dinilai', value: totalTidak, icon: <ShieldAlert size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <span className="mcr-table-meta">Periode:</span>
                            <select
                                className="mcr-filter-select"
                                value={semesterId}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setSemesterId(value);
                                    refreshByPeriod(value);
                                }}
                                disabled={isRefreshing || isSaving}
                            >
                                {semesters.map((semester) => (
                                    <option key={semester.id} value={String(semester.id)}>
                                        {semester.name}
                                    </option>
                                ))}
                            </select>
                            <span className="mcr-table-meta">Level:</span>
                            <select
                                className="mcr-filter-select"
                                value={levelId}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setLevelId(value);
                                    refreshByLevelId(value);
                                }}
                                disabled={isRefreshing || isSaving}
                            >
                                {levelOptions.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                            {isRefreshing ? (
                                <span className="mcr-table-meta">Memuat periode...</span>
                            ) : null}
                        </>
                    }
                />

                <CrudCard
                    title="Rule per Pelajaran"
                    subtitle="Kolom default menampilkan kebijakan level aktif, sementara override per kelas tetap tersedia."
                >
                    <div className="mcr-table-wrap">
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Pelajaran</th>
                                    <th>Default Level</th>
                                    <th>Wajib Nilai</th>
                                    <th>Tidak Dinilai</th>
                                    <th>Belum Diset</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((subject) => {
                                        const summary = subjectSummaryMap.get(subject.id) ?? {
                                            wajibCount: 0,
                                            tidakCount: 0,
                                            unsetCount: 0,
                                        };
                                        const levelDefault = defaultBySubject.get(subject.id);

                                        return (
                                            <tr key={subject.id}>
                                                <td style={{ fontWeight: 600 }}>{subject.name}</td>
                                                <td>
                                                    {levelDefault ? (
                                                        <span className={levelDefault.has_score_default ? 'mcr-dot-badge active' : 'mcr-dot-badge alumni'}>
                                                            {levelDefault.has_score_default ? 'Wajib dinilai' : 'Tidak dinilai'} • {levelDefault.target_jam_default} jam
                                                        </span>
                                                    ) : (
                                                        <span className="mcr-dot-badge keluar">Belum ada default</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <span className="mcr-dot-badge active">
                                                        {summary.wajibCount} kelas
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className="mcr-dot-badge alumni">
                                                        {summary.tidakCount} kelas
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className="mcr-dot-badge keluar">
                                                        {summary.unsetCount} kelas
                                                    </span>
                                                </td>
                                                <td>
                                                    <div className="mcr-action-group">
                                                        <button
                                                            type="button"
                                                            className="mcr-btn primary"
                                                            onClick={() => openEditor(subject)}
                                                            disabled={isRefreshing || isSaving}
                                                        >
                                                            Override per Kelas
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                            </tbody>
                        </table>
                    </div>
                </CrudCard>

                <div className="mcr-table-meta" style={{ marginTop: 10 }}>
                    Seluruh rule kosong saat ini: {totalBelum} sel.
                </div>
            </div>

            <CrudModal
                open={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingSubject ? `Atur Rule: ${editingSubject.name}` : 'Atur Rule'}
                subtitle="Pilih mode Include/Exclude, lalu tentukan tag tingkat target menggunakan multi-select."
                footer={
                    <>
                        <button
                            type="button"
                            className="mcr-btn ghost"
                            onClick={() => setIsModalOpen(false)}
                            disabled={isSaving}
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            className="mcr-btn primary"
                            onClick={saveBulkRule}
                            disabled={!editingSubject || isSaving}
                        >
                            {isSaving ? 'Menyimpan...' : 'Simpan Rule'}
                        </button>
                    </>
                }
            >
                <div className="mcr-form-grid">
                    <div className="mcr-form-group full">
                        <label htmlFor="mode">Mode Assignment</label>
                        <select
                            id="mode"
                            className="mcr-form-select"
                            value={mode}
                            onChange={(e) => setMode(e.target.value as ModeType)}
                            disabled={isSaving}
                        >
                            <option value="include">Include (yang dipilih = Wajib Nilai)</option>
                            <option value="exclude">Exclude (yang dipilih = Tidak Dinilai)</option>
                        </select>
                    </div>

                    <div className="mcr-form-group full">
                        <label htmlFor="level-multi-select">
                            {mode === 'include'
                                ? 'Pilih tag tingkat yang wajib dinilai'
                                : 'Pilih tag tingkat yang tidak dinilai'}
                        </label>
                        <AppMultiSelect
                            inputId="level-multi-select"
                            options={multiLevelOptions}
                            value={multiLevelOptions.filter((option) =>
                                selectedLookup.has(String(option.value)),
                            )}
                            onChange={(items) => {
                                const values = (items ?? []).map((item) => String(item.value));
                                setSelectedLevelIds(values);
                            }}
                            isDisabled={isSaving}
                            placeholder="Pilih jenjang..."
                            noOptionsMessage={() => 'Tidak ada jenjang'}
                        />
                    </div>

                    <div className="mcr-form-group">
                        <label>Preview Wajib Nilai ({previewWajib.length})</label>
                        <div className="mcr-run-item">
                            {previewWajib.length > 0
                                ? previewWajib.map((schoolClass) => schoolClass.name).join(', ')
                                : '-'}
                        </div>
                    </div>

                    <div className="mcr-form-group">
                        <label>Preview Tidak Dinilai ({previewTidak.length})</label>
                        <div className="mcr-run-item">
                            {previewTidak.length > 0
                                ? previewTidak.map((schoolClass) => schoolClass.name).join(', ')
                                : '-'}
                        </div>
                    </div>
                </div>

            </CrudModal>
        </AppLayout>
    );
}
