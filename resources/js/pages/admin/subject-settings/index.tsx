import { Head, router } from '@inertiajs/react';
import { BookOpen, Clock3, Layers3 } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import { AppSelect, CrudCard, CrudModal, CrudPageHeader, CrudStatStrip, CrudToolbar } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, GradeLevel, SchoolClass, Semester, Subject } from '@/types';

type LevelSettingRow = {
    id: number;
    level_id: number;
    subject_id: number;
    has_score_default: boolean;
    target_jam_default: number;
    is_mandatory_teaching: boolean;
    class_overrides: Array<{
        id: number;
        level_subject_default_id: number;
        class_id: number;
        override_hours: number;
    }>;
};

type Props = {
    subjects: Array<Pick<Subject, 'id' | 'name'>>;
    gradeSubjects: Array<{ id: number; grade_level_id: number; subject_id: number }>;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    levels: Pick<GradeLevel, 'id' | 'name' | 'order'>[];
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedSemesterId: number;
    levelSettings: LevelSettingRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subject Setting', href: '/admin/subject-settings' },
];

function effectiveWeeklyHoursForClass(
    subjectId: number,
    levelId: number,
    classId: number,
    lookup: Map<string, LevelSettingRow>,
): number {
    const setting = lookup.get(`${subjectId}:${levelId}`);
    if (setting && !setting.is_mandatory_teaching) {
        return 0;
    }
    const base = setting?.target_jam_default ?? 0;
    const override = setting?.class_overrides.find((row) => row.class_id === classId);
    return override ? override.override_hours : base;
}

export default function SubjectSettingsIndex({
    subjects,
    gradeSubjects,
    classes,
    levels,
    semesters,
    selectedSemesterId,
    levelSettings,
}: Props) {
    const [semesterId, setSemesterId] = useState(String(selectedSemesterId));
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [savingKey, setSavingKey] = useState<string | null>(null);
    const [overrideModal, setOverrideModal] = useState<{ subject: Pick<Subject, 'id' | 'name'>; levelId: number } | null>(null);
    const [overrideHours, setOverrideHours] = useState<Record<number, string>>({});

    const settingLookup = useMemo(() => {
        const lookup = new Map<string, LevelSettingRow>();
        levelSettings.forEach((item) => lookup.set(`${item.subject_id}:${item.level_id}`, item));
        return lookup;
    }, [levelSettings]);

    const subjectsByLevel = useMemo(() => {
        const map = new Map<number, Array<Pick<Subject, 'id' | 'name'>>>();
        levels.forEach((level) => {
            const ids = new Set(
                gradeSubjects
                    .filter((item) => item.grade_level_id === level.id)
                    .map((item) => item.subject_id),
            );
            map.set(level.id, subjects.filter((subject) => ids.has(subject.id)));
        });
        return map;
    }, [gradeSubjects, levels, subjects]);

    const jamSummaryPerLevel = useMemo(() => {
        const map = new Map<
            number,
            { total: number; defaultTotal: number; classCount: number; subjectCount: number; mandatorySubjectCount: number }
        >();
        for (const level of levels) {
            const levelSubjects = subjectsByLevel.get(level.id) ?? [];
            const levelClasses = classes.filter((c) => c.grade_level_id === level.id);
            let total = 0;
            let defaultTotal = 0;
            let mandatorySubjectCount = 0;
            for (const subject of levelSubjects) {
                const setting = settingLookup.get(`${subject.id}:${level.id}`);
                const isTaught = setting?.is_mandatory_teaching ?? true;
                if (!isTaught) {
                    continue;
                }
                mandatorySubjectCount += 1;
                defaultTotal += setting?.target_jam_default ?? 0;
            }
            for (const schoolClass of levelClasses) {
                for (const subject of levelSubjects) {
                    total += effectiveWeeklyHoursForClass(subject.id, level.id, schoolClass.id, settingLookup);
                }
            }
            map.set(level.id, {
                total,
                defaultTotal,
                classCount: levelClasses.length,
                subjectCount: levelSubjects.length,
                mandatorySubjectCount,
            });
        }
        return map;
    }, [levels, subjectsByLevel, classes, settingLookup]);

    const grandTotalJam = useMemo(
        () => [...jamSummaryPerLevel.values()].reduce((acc, row) => acc + row.total, 0),
        [jamSummaryPerLevel],
    );

    const grandDefaultJam = useMemo(
        () => [...jamSummaryPerLevel.values()].reduce((acc, row) => acc + row.defaultTotal, 0),
        [jamSummaryPerLevel],
    );

    function refreshSemester(nextSemesterId: string) {
        setIsRefreshing(true);
        router.get('/admin/subject-settings', { semester_id: nextSemesterId }, {
            preserveScroll: true,
            onFinish: () => setIsRefreshing(false),
        });
    }

    function saveLevelSetting(subjectId: number, levelId: number, patch: Partial<{ is_taught: boolean; default_hours: number; is_assessed: boolean }>) {
        const current = settingLookup.get(`${subjectId}:${levelId}`);
        setSavingKey(`${subjectId}:${levelId}`);
        router.post('/admin/subject-settings/level', {
            semester_id: Number(semesterId),
            subject_id: subjectId,
            level_id: levelId,
            is_taught: patch.is_taught ?? current?.is_mandatory_teaching ?? true,
            default_hours: patch.default_hours ?? current?.target_jam_default ?? 0,
            is_assessed: patch.is_assessed ?? current?.has_score_default ?? true,
        }, {
            preserveScroll: true,
            onFinish: () => setSavingKey(null),
        });
    }

    function saveOverride(classId: number) {
        if (!overrideModal) {
            return;
        }

        setSavingKey(`override:${classId}`);
        router.post('/admin/subject-settings/class-override', {
            semester_id: Number(semesterId),
            subject_id: overrideModal.subject.id,
            level_id: overrideModal.levelId,
            class_id: classId,
            override_hours: Number(overrideHours[classId] ?? 0),
        }, {
            preserveScroll: true,
            onFinish: () => setSavingKey(null),
        });
    }

    function deleteOverride(overrideId: number) {
        setSavingKey(`delete:${overrideId}`);
        router.delete(`/admin/subject-settings/class-override/${overrideId}`, {
            data: { semester_id: Number(semesterId) },
            preserveScroll: true,
            onFinish: () => setSavingKey(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subject Setting" />
            <div>
                <CrudPageHeader
                    title="Subject Setting"
                    description="Menampilkan semua tingkat sekaligus. Mapping mapel-tingkat diatur terpisah di halaman Mapping Mapel-Tingkat."
                />
                <CrudStatStrip
                    items={[
                        { key: 'subjects', label: 'Total Mapel', value: subjects.length, icon: <BookOpen size={18} />, tone: 'blue' },
                        { key: 'levels', label: 'Total Tingkat', value: levels.length, icon: <Layers3 size={18} />, tone: 'green' },
                        {
                            key: 'jam-default-total',
                            label: 'Total Jam Default (Σ default)',
                            value: grandDefaultJam,
                            icon: <Clock3 size={18} />,
                            tone: 'amber',
                        },
                        {
                            key: 'jam-total',
                            label: 'Total Jam (kelas × mapel)',
                            value: grandTotalJam,
                            icon: <Clock3 size={18} />,
                            tone: 'amber',
                        },
                        { key: 'overrides', label: 'Total Override', value: levelSettings.reduce((c, row) => c + row.class_overrides.length, 0), icon: <Clock3 size={18} />, tone: 'purple' },
                    ]}
                />
                <FlashMessage />
                <CrudToolbar
                    left={(
                        <>
                            <span className="mcr-table-meta">Semester:</span>
                            <AppSelect
                                value={(() => {
                                    const s = semesters.find((sem) => String(sem.id) === semesterId);
                                    return s ? { value: s.id, label: `${s.name} (${s.academic_year_name})` } : null;
                                })()}
                                options={semesters.map((semester) => ({ value: semester.id, label: `${semester.name} (${semester.academic_year_name})` }))}
                                onChange={(option) => {
                                    if (option) {
                                        setSemesterId(String(option.value));
                                        refreshSemester(String(option.value));
                                    }
                                }}
                                isDisabled={isRefreshing}
                                placeholder="Pilih semester..."
                            />
                        </>
                    )}
                    right={(
                        <button
                            type="button"
                            className="mcr-btn secondary"
                            onClick={() => router.visit('/admin/subject-level-mappings')}
                        >
                            Buka Mapping Mapel-Tingkat
                        </button>
                    )}
                />

                {levels.map((level) => {
                    const levelSubjects = subjectsByLevel.get(level.id) ?? [];
                    const jamInfo = jamSummaryPerLevel.get(level.id) ?? {
                        total: 0,
                        defaultTotal: 0,
                        classCount: 0,
                        subjectCount: 0,
                        mandatorySubjectCount: 0,
                    };
                    const defaultLine = `Σ Jam Default tingkat: ${jamInfo.defaultTotal} jam/minggu (per 1 kelas, dari ${jamInfo.mandatorySubjectCount} mapel “Dipelajari”).`;
                    const totalLine =
                        jamInfo.classCount === 0
                            ? `Total beban tingkat: ${jamInfo.total} jam/minggu (belum ada kelas di tingkat ini).`
                            : `Total beban tingkat: ${jamInfo.total} jam/minggu — ${jamInfo.classCount} kelas × ${jamInfo.mandatorySubjectCount} mapel (override per kelas dihitung).`;
                    const subtitle = `${defaultLine} | ${totalLine}`;
                    return (
                        <CrudCard key={level.id} title={`Setting ${level.name}`} subtitle={subtitle}>
                            <div className="mcr-table-wrap">
                                <table className="mcr-table">
                                    <thead>
                                        <tr>
                                            <th>Mapel</th>
                                            <th>Dipelajari</th>
                                            <th>Jam Default</th>
                                            <th>Dinilai</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {levelSubjects.map((subject) => {
                                            const setting = settingLookup.get(`${subject.id}:${level.id}`);
                                            return (
                                                <tr key={`${level.id}-${subject.id}`}>
                                                    <td style={{ fontWeight: 600 }}>{subject.name}</td>
                                                    <td>
                                                        <label className="mcr-switch">
                                                            <input
                                                                type="checkbox"
                                                                checked={setting?.is_mandatory_teaching ?? true}
                                                                onChange={(e) => saveLevelSetting(subject.id, level.id, { is_taught: e.target.checked })}
                                                                disabled={savingKey === `${subject.id}:${level.id}`}
                                                            />
                                                            <span>{(setting?.is_mandatory_teaching ?? true) ? 'Ya' : 'Tidak'}</span>
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <input
                                                            type="number"
                                                            className="mcr-input"
                                                            min={0}
                                                            max={24}
                                                            defaultValue={setting?.target_jam_default ?? 0}
                                                            onBlur={(e) => saveLevelSetting(subject.id, level.id, { default_hours: Number(e.target.value || 0) })}
                                                            disabled={savingKey === `${subject.id}:${level.id}`}
                                                        />
                                                    </td>
                                                    <td>
                                                        <label className="mcr-switch">
                                                            <input
                                                                type="checkbox"
                                                                checked={setting?.has_score_default ?? true}
                                                                onChange={(e) => saveLevelSetting(subject.id, level.id, { is_assessed: e.target.checked })}
                                                                disabled={savingKey === `${subject.id}:${level.id}`}
                                                            />
                                                            <span>{(setting?.has_score_default ?? true) ? 'Ya' : 'Tidak'}</span>
                                                        </label>
                                                    </td>
                                                    <td>
                                                        <div className="mcr-action-group">
                                                            <button
                                                                type="button"
                                                                className="mcr-btn primary"
                                                                onClick={() => setOverrideModal({ subject, levelId: level.id })}
                                                            >
                                                                Override Kelas
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
                    );
                })}
            </div>

            <CrudModal
                open={overrideModal !== null}
                onClose={() => setOverrideModal(null)}
                title={overrideModal ? `Override Jam - ${overrideModal.subject.name}` : 'Override Jam'}
            >
                <div className="mcr-form-grid">
                    {classes
                        .filter((schoolClass) => schoolClass.grade_level_id === overrideModal?.levelId)
                        .map((schoolClass) => {
                            const levelSetting = overrideModal
                                ? settingLookup.get(`${overrideModal.subject.id}:${overrideModal.levelId}`)
                                : null;
                            const override = levelSetting?.class_overrides.find((item) => item.class_id === schoolClass.id);
                            return (
                                <div key={schoolClass.id} className="mcr-form-group">
                                    <label>{schoolClass.name}</label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={24}
                                        className="mcr-input"
                                        value={overrideHours[schoolClass.id] ?? String(override?.override_hours ?? levelSetting?.target_jam_default ?? 0)}
                                        onChange={(e) => setOverrideHours((prev) => ({ ...prev, [schoolClass.id]: e.target.value }))}
                                    />
                                    <div className="mcr-action-group">
                                        <button type="button" className="mcr-btn primary" onClick={() => saveOverride(schoolClass.id)}>
                                            Simpan
                                        </button>
                                        {override ? (
                                            <button type="button" className="mcr-btn danger" onClick={() => deleteOverride(override.id)}>
                                                Hapus Override
                                            </button>
                                        ) : null}
                                    </div>
                                </div>
                            );
                        })}
                </div>
            </CrudModal>
        </AppLayout>
    );
}