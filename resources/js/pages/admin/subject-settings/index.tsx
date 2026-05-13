import { Head, router } from '@inertiajs/react';
import { BookOpen, Clock3, Layers3, LayoutGrid, X, ChevronRight } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import { AppSelect } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, GradeLevel, SchoolClass, Semester, Subject } from '@/types';

// ─── Types ────────────────────────────────────────────────────────────────────

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

// ─── Constants ────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subject Setting', href: '/admin/subject-settings' },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function effectiveWeeklyHoursForClass(
    subjectId: number,
    levelId: number,
    classId: number,
    lookup: Map<string, LevelSettingRow>,
): number {
    const setting = lookup.get(`${subjectId}:${levelId}`);
    if (setting && !setting.is_mandatory_teaching) return 0;
    const base = setting?.target_jam_default ?? 0;
    const override = setting?.class_overrides.find((row) => row.class_id === classId);
    return override ? override.override_hours : base;
}

// ─── Sub-components ───────────────────────────────────────────────────────────

type StatItem = {
    key: string;
    label: string;
    value: number;
    icon: React.ReactNode;
    tone: 'blue' | 'green' | 'amber' | 'purple';
};

const toneConfig: Record<StatItem['tone'], { bg: string; icon: string; badge: string }> = {
    blue:   { bg: 'bg-blue-50',   icon: 'text-blue-600',   badge: 'bg-blue-100 text-blue-700' },
    green:  { bg: 'bg-emerald-50', icon: 'text-emerald-600', badge: 'bg-emerald-100 text-emerald-700' },
    amber:  { bg: 'bg-amber-50',  icon: 'text-amber-600',  badge: 'bg-amber-100 text-amber-700' },
    purple: { bg: 'bg-violet-50', icon: 'text-violet-600', badge: 'bg-violet-100 text-violet-700' },
};

function StatStrip({ items }: { items: StatItem[] }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 mb-6">
            {items.map((item) => {
                const cfg = toneConfig[item.tone];
                return (
                    <div
                        key={item.key}
                        className={`${cfg.bg} rounded-xl p-4 flex flex-col gap-3 border border-black/5`}
                    >
                        <div className={`w-9 h-9 rounded-lg flex items-center justify-center ${cfg.badge}`}>
                            {item.icon}
                        </div>
                        <div>
                            <p className="text-2xl font-bold text-gray-900 leading-none">{item.value}</p>
                            <p className="text-xs text-gray-500 mt-1 leading-tight">{item.label}</p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function Toggle({
    checked,
    onChange,
    disabled,
    labelYes = 'Ya',
    labelNo = 'Tidak',
}: {
    checked: boolean;
    onChange: (val: boolean) => void;
    disabled?: boolean;
    labelYes?: string;
    labelNo?: string;
}) {
    return (
        <label className={`inline-flex items-center gap-2 ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}>
            <div className="relative">
                <input
                    type="checkbox"
                    className="sr-only peer"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                />
                <div className="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:shadow-sm after:transition-transform after:duration-200 peer-checked:after:translate-x-5" />
            </div>
            <span className={`text-sm font-medium ${checked ? 'text-blue-600' : 'text-gray-400'}`}>
                {checked ? labelYes : labelNo}
            </span>
        </label>
    );
}

function Modal({
    open,
    onClose,
    title,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: React.ReactNode;
}) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/40 backdrop-blur-sm"
                onClick={onClose}
            />
            {/* Panel */}
            <div className="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="text-base font-semibold text-gray-900">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        <X size={16} />
                    </button>
                </div>
                {/* Body */}
                <div className="overflow-y-auto flex-1 px-6 py-5">{children}</div>
            </div>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

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
                if (!isTaught) continue;
                mandatorySubjectCount += 1;
                defaultTotal += setting?.target_jam_default ?? 0;
            }
            for (const schoolClass of levelClasses) {
                for (const subject of levelSubjects) {
                    total += effectiveWeeklyHoursForClass(subject.id, level.id, schoolClass.id, settingLookup);
                }
            }
            map.set(level.id, { total, defaultTotal, classCount: levelClasses.length, subjectCount: levelSubjects.length, mandatorySubjectCount });
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

    function saveLevelSetting(
        subjectId: number,
        levelId: number,
        patch: Partial<{ is_taught: boolean; default_hours: number; is_assessed: boolean }>,
    ) {
        const current = settingLookup.get(`${subjectId}:${levelId}`);
        setSavingKey(`${subjectId}:${levelId}`);
        router.post('/admin/subject-settings/level', {
            semester_id: Number(semesterId),
            subject_id: subjectId,
            level_id: levelId,
            is_taught: patch.is_taught ?? current?.is_mandatory_teaching ?? true,
            default_hours: patch.default_hours ?? current?.target_jam_default ?? 0,
            is_assessed: patch.is_assessed ?? current?.has_score_default ?? true,
        }, { preserveScroll: true, onFinish: () => setSavingKey(null) });
    }

    function saveOverride(classId: number) {
        if (!overrideModal) return;
        setSavingKey(`override:${classId}`);
        router.post('/admin/subject-settings/class-override', {
            semester_id: Number(semesterId),
            subject_id: overrideModal.subject.id,
            level_id: overrideModal.levelId,
            class_id: classId,
            override_hours: Number(overrideHours[classId] ?? 0),
        }, { preserveScroll: true, onFinish: () => setSavingKey(null) });
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

            <div className="px-4 py-6 sm:px-6 lg:px-8 max-w-screen-2xl mx-auto space-y-6">

                {/* ── Page Header ── */}
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2 text-xs text-gray-400 font-medium">
                        <span>Dashboard</span>
                        <ChevronRight size={12} />
                        <span className="text-gray-600">Subject Setting</span>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Subject Setting</h1>
                    <p className="text-sm text-gray-500">
                        Menampilkan semua tingkat sekaligus. Mapping mapel‑tingkat diatur terpisah di halaman{' '}
                        <span className="font-medium text-gray-700">Mapping Mapel‑Tingkat</span>.
                    </p>
                </div>

                {/* ── Stat Strip ── */}
                <StatStrip
                    items={[
                        { key: 'subjects',        label: 'Total Mapel',                    value: subjects.length,           icon: <BookOpen size={16} />,  tone: 'blue'   },
                        { key: 'levels',          label: 'Total Tingkat',                  value: levels.length,             icon: <Layers3 size={16} />,   tone: 'green'  },
                        { key: 'jam-default',     label: 'Total Jam Default (Σ default)',  value: grandDefaultJam,           icon: <Clock3 size={16} />,    tone: 'amber'  },
                        { key: 'jam-total',       label: 'Total Jam (kelas × mapel)',      value: grandTotalJam,             icon: <Clock3 size={16} />,    tone: 'amber'  },
                        { key: 'overrides',       label: 'Total Override',                 value: levelSettings.reduce((c, r) => c + r.class_overrides.length, 0), icon: <LayoutGrid size={16} />, tone: 'purple' },
                    ]}
                />

                {/* ── Flash ── */}
                <FlashMessage />

                {/* ── Toolbar ── */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-white rounded-xl border border-gray-200 px-4 py-3 shadow-sm">
                    <div className="flex items-center gap-3 min-w-0">
                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider shrink-0">Semester</span>
                        <div className="min-w-[260px]">
                            <AppSelect
                                value={(() => {
                                    const s = semesters.find((sem) => String(sem.id) === semesterId);
                                    return s ? { value: s.id, label: `${s.name} (${s.academic_year_name})` } : null;
                                })()}
                                options={semesters.map((sem) => ({ value: sem.id, label: `${sem.name} (${sem.academic_year_name})` }))}
                                onChange={(option) => {
                                    if (option) {
                                        setSemesterId(String(option.value));
                                        refreshSemester(String(option.value));
                                    }
                                }}
                                isDisabled={isRefreshing}
                                placeholder="Pilih semester..."
                            />
                        </div>
                        {isRefreshing && (
                            <span className="text-xs text-gray-400 animate-pulse">Memuat…</span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => router.visit('/admin/subject-level-mappings')}
                        className="shrink-0 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all"
                    >
                        <LayoutGrid size={14} />
                        Buka Mapping Mapel‑Tingkat
                    </button>
                </div>

                {/* ── Level Cards ── */}
                {levels.map((level) => {
                    const levelSubjects = subjectsByLevel.get(level.id) ?? [];
                    const jamInfo = jamSummaryPerLevel.get(level.id) ?? {
                        total: 0, defaultTotal: 0, classCount: 0, subjectCount: 0, mandatorySubjectCount: 0,
                    };

                    return (
                        <div key={level.id} className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            {/* Card Header */}
                            <div className="px-6 py-4 border-b border-gray-100 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 className="text-base font-semibold text-gray-900">Setting {level.name}</h2>
                                    <div className="flex flex-wrap gap-3 mt-1.5">
                                        <span className="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                            <Clock3 size={12} className="text-amber-500" />
                                            Jam default: <strong className="text-gray-700">{jamInfo.defaultTotal} jam/minggu</strong>
                                            <span className="text-gray-400">({jamInfo.mandatorySubjectCount} mapel dipelajari)</span>
                                        </span>
                                        <span className="text-gray-300">·</span>
                                        <span className="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                            <Clock3 size={12} className="text-blue-500" />
                                            Total beban:{' '}
                                            <strong className="text-gray-700">{jamInfo.total} jam/minggu</strong>
                                            {jamInfo.classCount > 0 && (
                                                <span className="text-gray-400">({jamInfo.classCount} kelas)</span>
                                            )}
                                        </span>
                                    </div>
                                </div>
                                <span className="shrink-0 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                                    {levelSubjects.length} mapel
                                </span>
                            </div>

                            {/* Table */}
                            {levelSubjects.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-14 text-gray-400 gap-2">
                                    <BookOpen size={28} className="text-gray-300" />
                                    <p className="text-sm">Belum ada mapel di tingkat ini</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="bg-gray-50 border-b border-gray-100">
                                                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3 w-[40%]">Mapel</th>
                                                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Dipelajari</th>
                                                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Jam Default</th>
                                                <th className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Dinilai</th>
                                                <th className="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50">
                                            {levelSubjects.map((subject) => {
                                                const setting = settingLookup.get(`${subject.id}:${level.id}`);
                                                const isSaving = savingKey === `${subject.id}:${level.id}`;
                                                const isTaught = setting?.is_mandatory_teaching ?? true;

                                                return (
                                                    <tr
                                                        key={`${level.id}-${subject.id}`}
                                                        className={`group transition-colors hover:bg-blue-50/40 ${isSaving ? 'opacity-60' : ''}`}
                                                    >
                                                        {/* Name */}
                                                        <td className="px-6 py-3.5">
                                                            <span className={`font-semibold ${isTaught ? 'text-gray-900' : 'text-gray-400 line-through'}`}>
                                                                {subject.name}
                                                            </span>
                                                        </td>

                                                        {/* Toggle: Dipelajari */}
                                                        <td className="px-4 py-3.5">
                                                            <Toggle
                                                                checked={isTaught}
                                                                onChange={(val) => saveLevelSetting(subject.id, level.id, { is_taught: val })}
                                                                disabled={isSaving}
                                                            />
                                                        </td>

                                                        {/* Jam Default */}
                                                        <td className="px-4 py-3.5">
                                                            <div className="flex items-center gap-1.5">
                                                                <input
                                                                    type="number"
                                                                    min={0}
                                                                    max={24}
                                                                    defaultValue={setting?.target_jam_default ?? 0}
                                                                    onBlur={(e) => saveLevelSetting(subject.id, level.id, { default_hours: Number(e.target.value || 0) })}
                                                                    disabled={isSaving || !isTaught}
                                                                    className="w-20 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 text-center focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent disabled:opacity-40 disabled:cursor-not-allowed transition"
                                                                />
                                                                <span className="text-xs text-gray-400">jam</span>
                                                            </div>
                                                        </td>

                                                        {/* Toggle: Dinilai */}
                                                        <td className="px-4 py-3.5">
                                                            <Toggle
                                                                checked={setting?.has_score_default ?? true}
                                                                onChange={(val) => saveLevelSetting(subject.id, level.id, { is_assessed: val })}
                                                                disabled={isSaving}
                                                                labelYes="Ya"
                                                                labelNo="Tidak"
                                                            />
                                                        </td>

                                                        {/* Action */}
                                                        <td className="px-6 py-3.5 text-right">
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setOverrideHours({});
                                                                    setOverrideModal({ subject, levelId: level.id });
                                                                }}
                                                                disabled={isSaving}
                                                                className="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                                            >
                                                                <LayoutGrid size={12} />
                                                                Override Kelas
                                                            </button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* ── Override Modal ── */}
            <Modal
                open={overrideModal !== null}
                onClose={() => setOverrideModal(null)}
                title={overrideModal ? `Override Jam — ${overrideModal.subject.name}` : 'Override Jam'}
            >
                {overrideModal && (
                    <div className="space-y-3">
                        {classes
                            .filter((schoolClass) => schoolClass.grade_level_id === overrideModal.levelId)
                            .map((schoolClass) => {
                                const levelSetting = settingLookup.get(`${overrideModal.subject.id}:${overrideModal.levelId}`);
                                const override = levelSetting?.class_overrides.find((item) => item.class_id === schoolClass.id);
                                const isSavingOverride = savingKey === `override:${schoolClass.id}`;
                                const isSavingDelete  = savingKey === `delete:${override?.id}`;
                                const defaultVal = String(override?.override_hours ?? levelSetting?.target_jam_default ?? 0);

                                return (
                                    <div
                                        key={schoolClass.id}
                                        className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3"
                                    >
                                        {/* Class name + override badge */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-semibold text-gray-800 truncate">{schoolClass.name}</p>
                                                {override && (
                                                    <span className="shrink-0 text-[10px] font-bold uppercase tracking-wider text-violet-600 bg-violet-100 rounded px-1.5 py-0.5">
                                                        Override
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-gray-400">
                                                Default: {levelSetting?.target_jam_default ?? 0} jam/minggu
                                            </p>
                                        </div>

                                        {/* Hours input */}
                                        <div className="flex items-center gap-1">
                                            <input
                                                type="number"
                                                min={0}
                                                max={24}
                                                className="w-16 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm font-medium text-gray-800 text-center focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                                                value={overrideHours[schoolClass.id] ?? defaultVal}
                                                onChange={(e) =>
                                                    setOverrideHours((prev) => ({ ...prev, [schoolClass.id]: e.target.value }))
                                                }
                                            />
                                            <span className="text-xs text-gray-400">jam</span>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex items-center gap-1.5 shrink-0">
                                            <button
                                                type="button"
                                                disabled={isSavingOverride}
                                                onClick={() => saveOverride(schoolClass.id)}
                                                className="rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 px-3 py-1.5 text-xs font-semibold text-white transition-colors"
                                            >
                                                {isSavingOverride ? 'Menyimpan…' : 'Simpan'}
                                            </button>
                                            {override && (
                                                <button
                                                    type="button"
                                                    disabled={isSavingDelete}
                                                    onClick={() => deleteOverride(override.id)}
                                                    className="rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 disabled:opacity-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors"
                                                >
                                                    {isSavingDelete ? 'Menghapus…' : 'Hapus'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        {classes.filter((c) => c.grade_level_id === overrideModal.levelId).length === 0 && (
                            <p className="text-center text-sm text-gray-400 py-8">Belum ada kelas di tingkat ini.</p>
                        )}
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}