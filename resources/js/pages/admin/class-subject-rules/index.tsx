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
import type { BreadcrumbItem, Fan, SchoolClass, Semester, Subject } from '@/types';

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
    level_tag: string;
    subject_id: number;
    period_id: number;
    has_score_default: boolean;
    target_jam_default: number;
    is_mandatory_teaching: boolean;
};

type Props = {
    classes: Pick<SchoolClass, 'id' | 'name' | 'level'>[];
    subjects: Array<Pick<Subject, 'id' | 'name' | 'fan_id'> & { fan?: Pick<Fan, 'id' | 'name'> | null }>;
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
    rules: RuleRow[];
    levelDefaults: LevelDefaultRow[];
    selectedLevelTag: string;
    levelTagOptions: Array<{ value: string; label: string }>;
};

type ModeType = 'include' | 'exclude';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Rule Penilaian Mapel', href: '/admin/class-subject-rules' },
];

const levelTagLabelMap: Record<string, string> = {
    ibtida: 'Ibtida',
    '1salafy': 'Salafy 1',
    '2salafy': 'Salafy 2',
    '3salafy': 'Salafy 3',
    '4salafy': 'Salafy 4',
    '5salafy': 'Salafy 5',
    '6salafy': 'Salafy 6',
    '7salafy': 'Salafy 7',
    '8salafy': 'Salafy 8',
    '9salafy': 'Salafy 9',
    __untagged: 'Tanpa Tag Tingkat',
};

export default function ClassSubjectRulesIndex({
    classes,
    subjects,
    semesters,
    selectedPeriodId,
    selectedSemesterId,
    rules,
    levelDefaults,
    selectedLevelTag,
    levelTagOptions,
}: Props) {
    const [semesterId, setSemesterId] = useState(String(selectedSemesterId));
    const [levelTag, setLevelTag] = useState(selectedLevelTag);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<Pick<Subject, 'id' | 'name'> | null>(null);
    const [mode, setMode] = useState<ModeType>('include');
    const [selectedLevelTags, setSelectedLevelTags] = useState<string[]>([]);
    const [isSaving, setIsSaving] = useState(false);

    const classesByLevelTag = useMemo(() => {
        const map = new Map<string, Pick<SchoolClass, 'id' | 'name' | 'level'>[]>();
        classes.forEach((schoolClass) => {
            const key = schoolClass.level && schoolClass.level !== '' ? schoolClass.level : '__untagged';
            const current = map.get(key) ?? [];
            current.push(schoolClass);
            map.set(key, current);
        });

        return map;
    }, [classes]);

    const levelOptions = useMemo<SelectOption[]>(
        () =>
            Array.from(classesByLevelTag.entries())
                .map(([tag, cls]) => ({
                    value: tag,
                    label: `${levelTagLabelMap[tag] ?? tag} (${cls.length} kelas)`,
                }))
                .sort((a, b) => String(a.label).localeCompare(String(b.label), 'id-ID')),
        [classesByLevelTag],
    );

    const defaultBySubject = useMemo(() => {
        const map = new Map<number, LevelDefaultRow>();
        levelDefaults
            .filter((item) => item.level_tag === levelTag && item.period_id === selectedPeriodId)
            .forEach((item) => {
                map.set(item.subject_id, item);
            });

        return map;
    }, [levelDefaults, levelTag, selectedPeriodId]);

    const subjectSummaryMap = useMemo(() => {
        const map = new Map<number, { wajibCount: number; tidakCount: number; unsetCount: number }>();
        const classIdsInLevel = classes
            .filter((schoolClass) => (schoolClass.level && schoolClass.level !== '' ? schoolClass.level : '__untagged') === levelTag)
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
    }, [subjects, classes, rules, selectedPeriodId, levelTag]);

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

    const selectedLookup = useMemo(
        () => new Set(selectedLevelTags),
        [selectedLevelTags],
    );

    const groupedSubjects = useMemo(() => {
        const groups = new Map<string, { fanName: string; subjects: Props['subjects'] }>();
        subjects.forEach((subject) => {
            const fanName = subject.fan?.name ?? 'Tanpa Fan';
            const current = groups.get(fanName) ?? { fanName, subjects: [] };
            current.subjects.push(subject);
            groups.set(fanName, current);
        });

        return Array.from(groups.values()).sort((a, b) => a.fanName.localeCompare(b.fanName, 'id-ID'));
    }, [subjects]);

    const previewWajib = useMemo(() => {
        const inSelectedLevels = (item: Pick<SchoolClass, 'id' | 'name' | 'level'>) => {
            const tag = item.level && item.level !== '' ? item.level : '__untagged';
            return selectedLookup.has(tag);
        };

        if (mode === 'include') {
            return classes.filter((item) => inSelectedLevels(item));
        }

        return classes.filter((item) => !inSelectedLevels(item));
    }, [classes, mode, selectedLookup]);

    const previewTidak = useMemo(() => {
        const inSelectedLevels = (item: Pick<SchoolClass, 'id' | 'name' | 'level'>) => {
            const tag = item.level && item.level !== '' ? item.level : '__untagged';
            return selectedLookup.has(tag);
        };

        if (mode === 'include') {
            return classes.filter((item) => !inSelectedLevels(item));
        }

        return classes.filter((item) => inSelectedLevels(item));
    }, [classes, mode, selectedLookup]);

    function refreshByPeriod(nextPeriodId: string) {
        setIsRefreshing(true);
        router.get(
            '/admin/class-subject-rules',
            { semester_id: nextPeriodId, level_tag: levelTag },
            {
                preserveScroll: true,
                onFinish: () => setIsRefreshing(false),
            },
        );
    }

    function refreshByLevelTag(nextLevelTag: string) {
        setIsRefreshing(true);
        router.get(
            '/admin/class-subject-rules',
            { semester_id: semesterId, level_tag: nextLevelTag },
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

        const initialLevelTags: string[] = [];
        classesByLevelTag.forEach((levelClasses, tag) => {
            const allClassWajib = levelClasses.length > 0 && levelClasses.every((item) => wajibIds.has(item.id));
            if (allClassWajib) {
                initialLevelTags.push(tag);
            }
        });

        setEditingSubject(subject);
        setMode('include');
        setSelectedLevelTags(initialLevelTags);
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
                level_tags: selectedLevelTags,
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
                        { key: 'classes', label: `Kelas ${levelTagLabelMap[levelTag] ?? levelTag}`, value: classes.filter((item) => (item.level && item.level !== '' ? item.level : '__untagged') === levelTag).length, icon: <ListChecks size={18} />, tone: 'green' },
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
                                value={levelTag}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setLevelTag(value);
                                    refreshByLevelTag(value);
                                }}
                                disabled={isRefreshing || isSaving}
                            >
                                {levelTagOptions.map((item) => (
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
                    subtitle="Mapel dikelompokkan per Fan. Kolom default menampilkan kebijakan level aktif, sementara override per kelas tetap tersedia."
                >
                    <div className="mcr-table-wrap">
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Fan</th>
                                    <th>Pelajaran</th>
                                    <th>Default Level</th>
                                    <th>Wajib Nilai</th>
                                    <th>Tidak Dinilai</th>
                                    <th>Belum Diset</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {groupedSubjects.map((group) =>
                                    group.subjects.map((subject, index) => {
                                        const summary = subjectSummaryMap.get(subject.id) ?? {
                                            wajibCount: 0,
                                            tidakCount: 0,
                                            unsetCount: 0,
                                        };
                                        const levelDefault = defaultBySubject.get(subject.id);

                                        return (
                                            <tr key={subject.id}>
                                                <td>
                                                    {index === 0 ? <span className="mcr-dot-badge active">{group.fanName}</span> : ''}
                                                </td>
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
                                    })
                                )}
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
                            options={levelOptions}
                            value={levelOptions.filter((option) =>
                                selectedLookup.has(String(option.value)),
                            )}
                            onChange={(items) => {
                                const values = (items ?? []).map((item) => String(item.value));
                                setSelectedLevelTags(values);
                            }}
                            isDisabled={isSaving}
                            placeholder="Pilih tag tingkat..."
                            noOptionsMessage={() => 'Tidak ada tag tingkat'}
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
