import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Clock, Loader2, Move, Undo2, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    ScheduleConflictResponse,
    ScheduleMatrixCell,
    ScheduleMatrixPayload,
    ScheduleMatrixPengampu,
    ScheduleSet,
    ScheduleTimeSlot,
} from '@/types';
import ColorLegend from './editor-parts/ColorLegend';
import ConflictDialog from './editor-parts/ConflictDialog';
import type { ConflictAction } from './editor-parts/ConflictDialog';
import MatrixGrid from './editor-parts/MatrixGrid';
import PengampuPicker from './editor-parts/PengampuPicker';
import ActivityFeed from './editor-parts/ActivityFeed';
import PresenceBar from './editor-parts/PresenceBar';
import StatsBar from './editor-parts/StatsBar';
import SubjectPickDialog from './editor-parts/SubjectPickDialog';
import TimeSlotSettingsDialog from './editor-parts/TimeSlotSettingsDialog';
import { colorForTeacherIndex, teacherInitials } from './editor-parts/teacherColors';
import { useScheduleRealtime } from './editor-parts/useScheduleRealtime';

type Props = {
    scheduleSet: ScheduleSet;
    matrix: ScheduleMatrixPayload;
    pengampuList: ScheduleMatrixPengampu[];
};

type SubjectPickState = {
    day: number;
    jamNo: number;
    classId: number;
    className: string;
    options: ScheduleMatrixPengampu[];
};

type PengampuProgress = {
    allocated: number;
    target: number;
    remaining: number;
    isFull: boolean;
};

type UndoSnapshotItem = {
    class_id: number;
    subject_id: number;
    teacher_id: number;
    day: number;
    jam_no: number;
    time_start?: string;
    time_end?: string;
    combined_group_id?: string | null;
};

type UndoState = {
    label: string;
    items: UndoSnapshotItem[];
};

const dayLabels: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Ahad',
};

export default function ScheduleMatrixEditor({ scheduleSet, matrix: initialMatrix, pengampuList }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets' },
        { title: scheduleSet.name, href: `/admin/schedule-sets/${scheduleSet.id}/editor` },
    ];

    const page = usePage<{ auth?: { user?: { id?: number } } }>();
    const currentUserId = Number(page.props.auth?.user?.id ?? 0);
    const [matrix, setMatrix] = useState<ScheduleMatrixPayload>(initialMatrix);
    const [selectedTeacherIds, setSelectedTeacherIds] = useState<number[]>([]);
    const [subjectPick, setSubjectPick] = useState<SubjectPickState | null>(null);
    const [conflict, setConflict] = useState<ScheduleConflictResponse | null>(null);
    const [pendingAssign, setPendingAssign] = useState<{
        pengampuId: number;
        day: number;
        jamNo: number;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [lastUndo, setLastUndo] = useState<UndoState | null>(null);
    const [movingCell, setMovingCell] = useState<ScheduleMatrixCell | null>(null);
    const [activityItems, setActivityItems] = useState<Array<{ id: string; at: string; text: string }>>([]);
    const gridRef = useRef<HTMLDivElement | null>(null);
    const reloadTimerRef = useRef<number | null>(null);
    const { presence, lockMap, onEvent } = useScheduleRealtime(scheduleSet.id);

    useEffect(() => {
        setMatrix(initialMatrix);
    }, [initialMatrix]);

    const requestMatrixRefresh = useCallback(() => {
        if (reloadTimerRef.current != null) return;
        reloadTimerRef.current = window.setTimeout(() => {
            router.reload({ only: ['matrix'] });
            reloadTimerRef.current = null;
        }, 200);
    }, []);

    useEffect(() => {
        return () => {
            if (reloadTimerRef.current != null) {
                window.clearTimeout(reloadTimerRef.current);
                reloadTimerRef.current = null;
            }
        };
    }, []);

    const isMultiSelect = selectedTeacherIds.length > 1;
    const isSingleSelect = selectedTeacherIds.length === 1;
    const onlyTeacherId = isSingleSelect ? selectedTeacherIds[0] : null;

    useEffect(() => {
        if (matrix.slots.length === 0) {
            setSettingsOpen(true);
        }
    }, [matrix.slots.length]);

    function toggleTeacher(teacherId: number) {
        setSelectedTeacherIds((prev) =>
            prev.includes(teacherId) ? prev.filter((id) => id !== teacherId) : [...prev, teacherId],
        );
    }

    function clearTeachers() {
        setSelectedTeacherIds([]);
    }

    useEffect(() => {
        return onEvent(({ type, payload }) => {
            const byId = Number((payload.by as { id?: number } | undefined)?.id ?? 0);
            const byName = (payload.by as { name?: string } | undefined)?.name ?? 'Admin';

            const pushActivity = (text: string) => {
                setActivityItems((prev) => [
                    {
                        id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
                        at: new Date().toLocaleTimeString('id-ID'),
                        text,
                    },
                    ...prev,
                ].slice(0, 30));
            };

            if (type === 'cell.assigned') {
                const cell = payload.cell as ScheduleMatrixCell | undefined;
                if (!cell) {
                    requestMatrixRefresh();
                    return;
                }
                const day = Number((cell as Partial<ScheduleMatrixCell>).day ?? 0);
                const jamNo = Number((cell as Partial<ScheduleMatrixCell>).jam_no ?? 0);
                const classId = Number((cell as Partial<ScheduleMatrixCell>).class_id ?? 0);
                if (!day || !jamNo || !classId) {
                    requestMatrixRefresh();
                    return;
                }
                setMatrix((prev) => ({
                    ...prev,
                    cells: { ...prev.cells, [`${day}:${jamNo}:${classId}`]: cell },
                }));
                if (byId !== currentUserId) {
                    toast.info(`${byName} menambah jadwal ${cell.subject_name ?? '-'} (${cell.teacher_name ?? '-'})`);
                }
                pushActivity(`${byName} menambah ${cell.subject_name ?? '-'} pada ${dayLabels[cell.day] ?? `Hari ${cell.day}`} jam ${cell.jam_no}`);
                requestMatrixRefresh();

                return;
            }

            if (type === 'cell.deleted') {
                const cell = payload.cell as { class_id: number; day: number; jam_no: number } | undefined;
                if (!cell) {
                    requestMatrixRefresh();
                    return;
                }
                const day = Number(cell.day ?? 0);
                const jamNo = Number(cell.jam_no ?? 0);
                const classId = Number(cell.class_id ?? 0);
                if (!day || !jamNo || !classId) {
                    requestMatrixRefresh();
                    return;
                }
                const key = `${day}:${jamNo}:${classId}`;
                setMatrix((prev) => {
                    if (!prev.cells[key]) return prev;
                    const next = { ...prev.cells };
                    delete next[key];

                    return { ...prev, cells: next };
                });
                if (byId !== currentUserId) {
                    toast.info(`${byName} menghapus satu cell jadwal`);
                }
                pushActivity(`${byName} menghapus cell pada ${dayLabels[cell.day] ?? `Hari ${cell.day}`} jam ${cell.jam_no}`);
                requestMatrixRefresh();

                return;
            }

            if (type === 'cell.moved') {
                const sourceOld = payload.source_old as { class_id: number; day: number; jam_no: number } | undefined;
                const sourceNew = payload.source_new as ScheduleMatrixCell | undefined;
                const targetOld = payload.target_old as { class_id: number; day: number; jam_no: number } | undefined;
                const targetNew = payload.target_new as ScheduleMatrixCell | undefined;

                setMatrix((prev) => {
                    const next = { ...prev.cells };
                    if (sourceOld) delete next[`${sourceOld.day}:${sourceOld.jam_no}:${sourceOld.class_id}`];
                    if (targetOld) delete next[`${targetOld.day}:${targetOld.jam_no}:${targetOld.class_id}`];
                    if (sourceNew) next[`${sourceNew.day}:${sourceNew.jam_no}:${sourceNew.class_id}`] = sourceNew;
                    if (targetNew) next[`${targetNew.day}:${targetNew.jam_no}:${targetNew.class_id}`] = targetNew;

                    return { ...prev, cells: next };
                });
                if (byId !== currentUserId) {
                    toast.info(`${byName} memindahkan cell jadwal`);
                }
                pushActivity(`${byName} memindahkan cell jadwal`);
                requestMatrixRefresh();

                return;
            }

            if (type === 'cells.bulk_deleted' || type === 'cells.restored' || type === 'time_slots.updated') {
                requestMatrixRefresh();
                if (byId !== currentUserId) {
                    toast.info(`${byName} memperbarui data jadwal`);
                }
                pushActivity(`${byName} memperbarui data jadwal (${type})`);
            }
        });
    }, [currentUserId, onEvent, requestMatrixRefresh]);

    /** Union dari kelas yang diampu semua guru terpilih (urut sesuai matrix.classes). */
    const teacherClassIds = useMemo(() => {
        if (selectedTeacherIds.length === 0) return null;
        const teacherSet = new Set(selectedTeacherIds);
        const ids = new Set<number>();
        for (const p of pengampuList) {
            if (teacherSet.has(p.teacher_id)) {
                ids.add(p.class_id);
            }
        }
        return matrix.classes.filter((c) => ids.has(c.id)).map((c) => c.id);
    }, [selectedTeacherIds, pengampuList, matrix.classes]);

    const onlyTeacherName = useMemo(() => {
        if (onlyTeacherId == null) return null;
        const p = pengampuList.find((x) => x.teacher_id === onlyTeacherId);
        return p?.teacher?.name ?? `Guru #${onlyTeacherId}`;
    }, [onlyTeacherId, pengampuList]);

    const pendingPengampu = useMemo(() => {
        if (!pendingAssign) return null;
        return pengampuList.find((p) => p.id === pendingAssign.pengampuId) ?? null;
    }, [pendingAssign, pengampuList]);

    const progressByPengampuId = useMemo<Record<number, PengampuProgress>>(() => {
        const allocatedMap = new Map<string, number>();
        for (const cell of Object.values(matrix.cells)) {
            const key = `${cell.teacher_id}:${cell.class_id}:${cell.subject_id}`;
            allocatedMap.set(key, (allocatedMap.get(key) ?? 0) + 1);
        }

        const out: Record<number, PengampuProgress> = {};
        for (const p of pengampuList) {
            const key = `${p.teacher_id}:${p.class_id}:${p.subject_id}`;
            const allocated = allocatedMap.get(key) ?? 0;
            const target = Math.max(1, p.target_jam_effective ?? p.target_jam ?? 1);
            out[p.id] = {
                allocated,
                target,
                remaining: Math.max(0, target - allocated),
                isFull: allocated >= target,
            };
        }
        return out;
    }, [matrix.cells, pengampuList]);

    const initialSlots: ScheduleTimeSlot[] = useMemo(
        () =>
            matrix.slots.map((s) => ({
                jam_no: s.jam_no,
                time_start: s.time_start,
                time_end: s.time_end,
            })),
        [matrix.slots],
    );

    const unmetCount = useMemo(
        () => Object.values(progressByPengampuId).filter((item) => item.remaining > 0).length,
        [progressByPengampuId],
    );

    const stats = useMemo(() => {
        const totalSlots = matrix.days.length * matrix.slots.length * matrix.classes.length;
        const filledCount = Object.keys(matrix.cells).length;

        const teacherSlotMap = new Map<string, number>();
        for (const cell of Object.values(matrix.cells)) {
            if (cell.combined_group_id) continue;
            const k = `${cell.teacher_id}:${cell.day}:${cell.jam_no}`;
            teacherSlotMap.set(k, (teacherSlotMap.get(k) ?? 0) + 1);
        }
        let teacherClashes = 0;
        for (const v of teacherSlotMap.values()) {
            if (v > 1) teacherClashes++;
        }

        const pengampuTotal = pengampuList.length;
        const pengampuFulfilled = Object.values(progressByPengampuId).filter((p) => p.isFull).length;

        return { totalSlots, filledCount, teacherClashes, pengampuTotal, pengampuFulfilled };
    }, [
        matrix.days.length,
        matrix.slots.length,
        matrix.classes.length,
        matrix.cells,
        pengampuList.length,
        progressByPengampuId,
    ]);

    const subjectsInUse = useMemo(() => {
        const seen = new Map<number, string>();
        for (const cell of Object.values(matrix.cells)) {
            if (!seen.has(cell.subject_id)) {
                seen.set(cell.subject_id, cell.subject_name ?? `Mapel #${cell.subject_id}`);
            }
        }
        return [...seen.entries()]
            .map(([subject_id, subject_name]) => ({ subject_id, subject_name }))
            .sort((a, b) => a.subject_name.localeCompare(b.subject_name, 'id'));
    }, [matrix.cells]);

    const teacherNameById = useMemo(() => {
        const map = new Map<number, string>();
        for (const p of pengampuList) {
            if (!map.has(p.teacher_id) && p.teacher?.name) {
                map.set(p.teacher_id, p.teacher.name);
            }
        }
        return map;
    }, [pengampuList]);

    const pengampuForClass = useCallback(
        (classId: number): ScheduleMatrixPengampu[] => {
            if (onlyTeacherId == null) return [];
            return pengampuList.filter(
                (p) => p.teacher_id === onlyTeacherId && p.class_id === classId,
            );
        },
        [onlyTeacherId, pengampuList],
    );

    async function withCellLock<T>(
        slot: { classId: number; day: number; jamNo: number },
        run: (lockToken: string) => Promise<T>,
    ): Promise<T> {
        const csrf = getCsrfToken();
        const acquire = await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/lock`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                class_id: slot.classId,
                day: slot.day,
                jam_no: slot.jamNo,
            }),
        });

        if (!acquire.ok) {
            const payload = (await acquire.json().catch(() => ({}))) as {
                message?: string;
                holder?: { user_name?: string };
            };
            if (acquire.status === 423) {
                throw new Error(payload.message ?? `Slot sedang dipakai ${payload.holder?.user_name ?? 'admin lain'}.`);
            }
            throw new Error(payload.message ?? 'Gagal acquire lock.');
        }

        const lockData = (await acquire.json()) as { token: string };
        const lockToken = lockData.token;
        const heartbeat = window.setInterval(() => {
            void fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/lock/heartbeat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    class_id: slot.classId,
                    day: slot.day,
                    jam_no: slot.jamNo,
                    token: lockToken,
                }),
            });
        }, 4000);

        try {
            return await run(lockToken);
        } finally {
            window.clearInterval(heartbeat);
            await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/lock`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    class_id: slot.classId,
                    day: slot.day,
                    jam_no: slot.jamNo,
                    token: lockToken,
                }),
            }).catch(() => undefined);
        }
    }

    function postAssign(
        action: ConflictAction,
        target: { pengampuId: number; day: number; jamNo: number },
        lockToken?: string,
    ): Promise<void> {
        setBusy(true);

        return new Promise((resolve) => {
            router.post(
                `/admin/schedule-sets/${scheduleSet.id}/cells`,
                {
                    pengampu_id: target.pengampuId,
                    day: target.day,
                    jam_no: target.jamNo,
                    action,
                },
                {
                    headers: lockToken ? { 'X-Schedule-Lock-Token': lockToken } : undefined,
                    preserveScroll: true,
                    onFinish: () => {
                        setBusy(false);
                        setConflict(null);
                        setPendingAssign(null);
                        resolve();
                    },
                },
            );
        });
    }

    async function runPreflightAndAssign(pengampuId: number, day: number, jamNo: number) {
        const targetPengampu = pengampuList.find((item) => item.id === pengampuId);
        if (!targetPengampu) {
            alert('Data pengampu tidak ditemukan.');
            return;
        }

        setBusy(true);
        try {
            const res = await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/preflight`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ pengampu_id: pengampuId, day, jam_no: jamNo }),
            });

            if (!res.ok) throw new Error('Preflight gagal');
            const data = (await res.json()) as ScheduleConflictResponse;

            setBusy(false);
            if (data.type === 'target_reached') {
                alert(
                    `Target jam sudah terpenuhi (${data.allocation ?? 0}/${data.target_jam ?? 0}). Hapus atau pindahkan slot lain jika ingin mengubah.`,
                );
                return;
            }
            if (data.type === 'none') {
                await withCellLock(
                    { classId: targetPengampu.class_id, day, jamNo },
                    async (token) => postAssign('assign', { pengampuId, day, jamNo }, token),
                );
                return;
            }
            setConflict(data);
            setPendingAssign({ pengampuId, day, jamNo });
        } catch (e) {
            console.error(e);
            alert('Gagal memeriksa konflik. Coba lagi.');
            setBusy(false);
        }
    }

    async function handleClickCell(day: number, jamNo: number, classId: number) {
        if (movingCell) {
            await performMove(movingCell, classId, day, jamNo);
            return;
        }

        const cellKey = `${day}:${jamNo}:${classId}`;
        const existingCell = matrix.cells[cellKey];

        if (selectedTeacherIds.length === 0) {
            if (existingCell && confirm(`Hapus cell ${existingCell.teacher_name} - ${existingCell.subject_name}?`)) {
                handleDeleteCell(existingCell);
            }
            return;
        }

        if (isMultiSelect) {
            if (existingCell && confirm(`Hapus cell ${existingCell.teacher_name} - ${existingCell.subject_name}?`)) {
                handleDeleteCell(existingCell);
            }
            return;
        }

        if (teacherClassIds && !teacherClassIds.includes(classId)) {
            return;
        }

        const matches = pengampuForClass(classId);
        if (matches.length === 0) return;

        if (matches.length === 1) {
            const singleProgress = progressByPengampuId[matches[0].id];
            if (singleProgress?.isFull) {
                alert(`Target jam mapel ini sudah penuh (${singleProgress.allocated}/${singleProgress.target}).`);
                return;
            }
            await runPreflightAndAssign(matches[0].id, day, jamNo);
            return;
        }

        const cls = matrix.classes.find((c) => c.id === classId);
        setSubjectPick({
            day,
            jamNo,
            classId,
            className: cls?.name ?? `Kelas #${classId}`,
            options: matches.sort((a, b) => {
                const ap = progressByPengampuId[a.id];
                const bp = progressByPengampuId[b.id];
                const remainingDiff = (bp?.remaining ?? 0) - (ap?.remaining ?? 0);
                if (remainingDiff !== 0) return remainingDiff;
                return (a.subject?.name ?? '').localeCompare(b.subject?.name ?? '', 'id');
            }),
        });
    }

    function cancelConflict() {
        setConflict(null);
        setPendingAssign(null);
    }

    async function confirmConflict(action: ConflictAction) {
        if (!pendingAssign) return;
        const targetPengampu = pengampuList.find((item) => item.id === pendingAssign.pengampuId);
        if (!targetPengampu) {
            alert('Data pengampu tidak ditemukan.');
            return;
        }
        try {
            await withCellLock(
                { classId: targetPengampu.class_id, day: pendingAssign.day, jamNo: pendingAssign.jamNo },
                async (token) => postAssign(action, pendingAssign, token),
            );
        } catch (error) {
            alert((error as Error).message || 'Gagal assign dengan lock.');
        }
    }

    async function handleDeleteCell(cell: ScheduleMatrixCell) {
        const isCombined = !!cell.combined_group_id;
        let deleteGroup = false;
        if (isCombined) {
            const ans = window.confirm(
                'Cell ini bagian dari kelas digabung. Tekan OK untuk menghapus SELURUH grup gabungan, atau Batal untuk menghapus cell ini saja.',
            );
            deleteGroup = ans;
        }

        setBusy(true);
        try {
            const res = await withCellLock(
                { classId: cell.class_id, day: cell.day, jamNo: cell.jam_no },
                async (token) =>
                    fetch(
                        `/admin/schedule-sets/${scheduleSet.id}/cells/${cell.schedule_id}` +
                            (deleteGroup ? '?delete_group=1' : ''),
                        {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                                'X-Schedule-Lock-Token': token,
                            },
                        },
                    ),
            );
            if (!res.ok) throw new Error('Gagal hapus cell');
            const data = (await res.json()) as { snapshot: UndoSnapshotItem[]; deleted: number };
            setLastUndo({
                label: deleteGroup
                    ? `${data.deleted} cell (grup gabungan)`
                    : `1 cell (${cell.teacher_name ?? '-'} · ${cell.subject_name ?? '-'})`,
                items: data.snapshot,
            });
        } catch (e) {
            console.error(e);
            alert((e as Error).message || 'Gagal menghapus cell.');
        } finally {
            setBusy(false);
        }
    }

    async function performMove(
        source: ScheduleMatrixCell,
        targetClassId: number,
        targetDay: number,
        targetJamNo: number,
    ) {
        if (
            source.class_id === targetClassId &&
            source.day === targetDay &&
            source.jam_no === targetJamNo
        ) {
            setMovingCell(null);
            return;
        }

        setBusy(true);
        try {
            const res = await withCellLock(
                { classId: source.class_id, day: source.day, jamNo: source.jam_no },
                async (token) =>
                    fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/move`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Schedule-Lock-Token': token,
                        },
                        body: JSON.stringify({
                            source_schedule_id: source.schedule_id,
                            target_class_id: targetClassId,
                            target_day: targetDay,
                            target_jam_no: targetJamNo,
                            mode: 'auto',
                        }),
                    }),
            );
            if (!res.ok) {
                const errBody = (await res.json().catch(() => ({}))) as { message?: string };
                throw new Error(errBody.message ?? 'Gagal memindahkan cell');
            }
            const data = (await res.json()) as {
                mode: 'move' | 'swap' | 'replace';
                source_snapshot: UndoSnapshotItem[];
                target_snapshot: UndoSnapshotItem[];
            };
            const undoItems = [...data.source_snapshot, ...data.target_snapshot];
            const label =
                data.mode === 'swap'
                    ? `swap ${source.teacher_name ?? '-'} ↔ cell tujuan`
                    : data.mode === 'replace'
                      ? `pindah ${source.teacher_name ?? '-'} (replace tujuan)`
                      : `pindah ${source.teacher_name ?? '-'}`;
            if (undoItems.length > 0) {
                setLastUndo({ label, items: undoItems });
            }
        } catch (e) {
            const err = e as Error;
            alert(`Gagal memindahkan cell: ${err.message ?? 'unknown error'}`);
        } finally {
            setBusy(false);
            setMovingCell(null);
        }
    }

    async function bulkDelete(
        scope: 'day' | 'jam' | 'class' | 'day_jam',
        params: { day?: number; jam_no?: number; class_id?: number },
        label: string,
    ) {
        const cellsAffected = Object.values(matrix.cells).filter((c) => {
            if (params.day != null && c.day !== params.day) return false;
            if (params.jam_no != null && c.jam_no !== params.jam_no) return false;
            if (params.class_id != null && c.class_id !== params.class_id) return false;
            return true;
        });
        if (cellsAffected.length === 0) {
            alert(`Tidak ada cell terisi untuk dihapus pada ${label}.`);
            return;
        }
        if (!window.confirm(`Hapus ${cellsAffected.length} cell pada ${label}? Bisa di-undo setelahnya.`)) {
            return;
        }

        setBusy(true);
        try {
            const res = await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/bulk-delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ scope, ...params }),
            });
            if (!res.ok) throw new Error('Bulk delete gagal');
            const data = (await res.json()) as { deleted: number; snapshot: UndoSnapshotItem[] };
            setLastUndo({
                label: `${data.deleted} cell di ${label}`,
                items: data.snapshot,
            });
            router.reload({ only: ['matrix'] });
        } catch (e) {
            console.error(e);
            alert('Gagal melakukan bulk delete.');
        } finally {
            setBusy(false);
        }
    }

    async function performUndo() {
        if (!lastUndo || lastUndo.items.length === 0) return;
        setBusy(true);
        try {
            const res = await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/restore`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ items: lastUndo.items }),
            });
            if (!res.ok) throw new Error('Restore gagal');
            const data = (await res.json()) as { restored: number; skipped: number; errors: string[] };
            if (data.skipped > 0) {
                alert(
                    `Undo dipulihkan ${data.restored}, dilewati ${data.skipped}.\n` +
                        (data.errors.slice(0, 5).join('\n') || ''),
                );
            }
            setLastUndo(null);
            router.reload({ only: ['matrix'] });
        } catch (e) {
            console.error(e);
            alert('Gagal undo.');
        } finally {
            setBusy(false);
        }
    }

    async function onSubjectPicked(pengampu: ScheduleMatrixPengampu) {
        if (!subjectPick) return;
        const { day, jamNo } = subjectPick;
        setSubjectPick(null);
        await runPreflightAndAssign(pengampu.id, day, jamNo);
    }

    function handleDeleteClassColumn(classId: number) {
        const cls = matrix.classes.find((c) => c.id === classId);
        bulkDelete('class', { class_id: classId }, `kelas ${cls?.name ?? `#${classId}`}`);
    }
    function handleDeleteDay(day: number) {
        bulkDelete('day', { day }, `hari ${dayLabels[day] ?? day}`);
    }
    function handleDeleteDayJam(day: number, jamNo: number) {
        bulkDelete('day_jam', { day, jam_no: jamNo }, `${dayLabels[day] ?? day} jam ${jamNo}`);
    }

    /** Auto-scroll: saat single-select pilih guru, scroll ke kelas pertama yang diampu. */
    useEffect(() => {
        if (!isSingleSelect || !teacherClassIds || teacherClassIds.length === 0 || !gridRef.current) {
            return;
        }
        const firstClassId = teacherClassIds[0];
        const target = gridRef.current.querySelector(`th[data-class-col="${firstClassId}"]`) as HTMLElement | null;
        if (target) {
            const container = gridRef.current;
            const left = target.offsetLeft - 100;
            container.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
        }
    }, [isSingleSelect, teacherClassIds]);

    /** Esc → batal mode pindah. */
    useEffect(() => {
        if (!movingCell) return;
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') setMovingCell(null);
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [movingCell]);

    useEffect(() => {
        function onBeforeUnload() {
            const csrf = getCsrfToken();
            const body = JSON.stringify({ _token: csrf });
            navigator.sendBeacon(
                `/admin/schedule-sets/${scheduleSet.id}/cells/lock/release-all`,
                new Blob([body], { type: 'application/json' }),
            );
        }
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [scheduleSet.id]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editor — ${scheduleSet.name}`} />
            <div className="flex h-full flex-1 flex-col gap-3 p-4">
                <FlashMessage />

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/schedule-sets">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Kembali
                            </Link>
                        </Button>
                        <Heading
                            title={scheduleSet.name}
                            description={
                                scheduleSet.period?.semester?.name
                                    ? `Periode ${scheduleSet.period.semester.name}`
                                    : undefined
                            }
                        />
                        {scheduleSet.is_active ? (
                            <Badge>Aktif</Badge>
                        ) : (
                            <Badge variant="secondary">Draft</Badge>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {lastUndo && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={performUndo}
                                disabled={busy}
                                title={`Undo: ${lastUndo.label}`}
                            >
                                <Undo2 className="mr-1 h-4 w-4" />
                                Undo ({lastUndo.items.length})
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setSettingsOpen(true)}
                        >
                            <Clock className="mr-1 h-4 w-4" />
                            Pengaturan jam ({scheduleSet.jam_count} jam × {scheduleSet.day_count} hari)
                        </Button>
                        <Badge variant={unmetCount > 0 ? 'secondary' : 'default'}>
                            {unmetCount > 0 ? `${unmetCount} pengampu belum memenuhi target` : 'Semua target terpenuhi'}
                        </Badge>
                    </div>
                </div>

                <PresenceBar members={presence} currentUserId={currentUserId} />

                {movingCell && (
                    <div className="flex items-center justify-between gap-2 rounded-md border border-primary/40 bg-primary/5 px-3 py-2 text-xs">
                        <span className="inline-flex items-center gap-2">
                            <Move className="h-4 w-4 text-primary" />
                            <span>
                                Mode <strong>Pindahkan</strong>: klik slot tujuan untuk menempatkan{' '}
                                <strong>
                                    {movingCell.teacher_name ?? '-'} · {movingCell.subject_name ?? '-'}
                                </strong>
                                . Slot terisi akan di-<strong>swap</strong> otomatis.
                            </span>
                        </span>
                        <Button type="button" variant="ghost" size="sm" onClick={() => setMovingCell(null)}>
                            <X className="mr-1 h-3 w-3" />
                            Batal (Esc)
                        </Button>
                    </div>
                )}

                <StatsBar
                    totalSlots={stats.totalSlots}
                    filledCount={stats.filledCount}
                    teacherClashes={stats.teacherClashes}
                    pengampuTotal={stats.pengampuTotal}
                    pengampuFulfilled={stats.pengampuFulfilled}
                />

                <ColorLegend subjects={subjectsInUse} />

                <div className="grid flex-1 grid-cols-[260px_1fr] gap-3 overflow-hidden">
                    <aside className="flex flex-col gap-2 overflow-hidden">
                        <div className="text-sm font-semibold">Guru (pengampu)</div>
                        <PengampuPicker
                            pengampuList={pengampuList}
                            selectedTeacherIds={selectedTeacherIds}
                            onToggleTeacher={toggleTeacher}
                            onClearSelection={clearTeachers}
                            progressByPengampuId={progressByPengampuId}
                        />
                        <InstructionPanel
                            selectedTeacherIds={selectedTeacherIds}
                            onlyTeacherName={onlyTeacherName}
                            teacherNameById={teacherNameById}
                            teacherClassIds={teacherClassIds}
                            matrix={matrix}
                            pengampuList={pengampuList}
                            progressByPengampuId={progressByPengampuId}
                        />
                        <ActivityFeed items={activityItems} />
                    </aside>
                    <main className="flex-1 overflow-hidden">
                        {matrix.slots.length === 0 ? (
                            <div className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                                Slot jam belum dikonfigurasi. Klik tombol "Pengaturan jam" di atas untuk mengisi
                                waktu tiap jam ke-N.
                            </div>
                        ) : (
                            <MatrixGrid
                                ref={gridRef}
                                matrix={matrix}
                                selectedTeacherIds={selectedTeacherIds}
                                highlightedClassIds={teacherClassIds ?? []}
                                visibleClassIds={teacherClassIds ?? undefined}
                                onClickCell={handleClickCell}
                                onDeleteCell={handleDeleteCell}
                                onMoveStart={(cell) => setMovingCell(cell)}
                                onMoveDrop={(sid, classId, day, jamNo) => {
                                    const source =
                                        Object.values(matrix.cells).find((c) => c.schedule_id === sid) ?? null;
                                    if (!source) return;
                                    void performMove(source, classId, day, jamNo);
                                }}
                                onDeleteClassColumn={handleDeleteClassColumn}
                                onDeleteDay={handleDeleteDay}
                                onDeleteDayJam={handleDeleteDayJam}
                                movingCellId={movingCell?.schedule_id ?? null}
                                lockMap={lockMap}
                                currentUserId={currentUserId}
                            />
                        )}
                    </main>
                </div>

                <SubjectPickDialog
                    open={!!subjectPick}
                    className={subjectPick?.className ?? ''}
                    options={subjectPick?.options ?? []}
                    progressByPengampuId={progressByPengampuId}
                    onPick={onSubjectPicked}
                    onCancel={() => setSubjectPick(null)}
                />

                <ConflictDialog
                    conflict={conflict}
                    open={!!conflict}
                    onCancel={cancelConflict}
                    onConfirm={confirmConflict}
                    busy={busy}
                    context={
                        pendingAssign && pendingPengampu
                            ? {
                                  dayLabel:
                                      dayLabels[pendingAssign.day] ?? `Hari ${pendingAssign.day}`,
                                  jamNo: pendingAssign.jamNo,
                                  teacherName:
                                      pendingPengampu.teacher?.name ??
                                      `Guru #${pendingPengampu.teacher_id}`,
                                  subjectName:
                                      pendingPengampu.subject?.name ??
                                      `Mapel #${pendingPengampu.subject_id}`,
                                  targetClassName:
                                      pendingPengampu.school_class?.name ??
                                      `Kelas #${pendingPengampu.class_id}`,
                              }
                            : undefined
                    }
                />

                <TimeSlotSettingsDialog
                    open={settingsOpen}
                    onOpenChange={setSettingsOpen}
                    scheduleSetId={scheduleSet.id}
                    initialJamCount={scheduleSet.jam_count}
                    initialDayCount={scheduleSet.day_count}
                    initialSlots={initialSlots}
                />

                {busy && (
                    <div className="fixed bottom-4 right-4 flex items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm shadow">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Memproses...
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function InstructionPanel({
    selectedTeacherIds,
    onlyTeacherName,
    teacherNameById,
    teacherClassIds,
    matrix,
    pengampuList,
    progressByPengampuId,
}: {
    selectedTeacherIds: number[];
    onlyTeacherName: string | null;
    teacherNameById: Map<number, string>;
    teacherClassIds: number[] | null;
    matrix: ScheduleMatrixPayload;
    pengampuList: ScheduleMatrixPengampu[];
    progressByPengampuId: Record<number, PengampuProgress>;
}) {
    if (selectedTeacherIds.length === 0) {
        return (
            <div className="rounded-md border bg-muted/30 p-2 text-xs text-muted-foreground">
                Pilih guru di daftar untuk filter & assign. Pilih ≥2 guru untuk masuk <strong>Compare mode</strong>:
                matrix menampilkan gabungan kelas semua guru terpilih, tiap guru dapat warna berbeda.
                Klik cell terisi (tanpa guru aktif tunggal) untuk menghapus.
            </div>
        );
    }

    const isMulti = selectedTeacherIds.length > 1;
    const names =
        teacherClassIds
            ?.map((id) => matrix.classes.find((c) => c.id === id)?.name)
            .filter(Boolean)
            .join(', ') ?? '';

    if (isMulti) {
        return (
            <div className="rounded-md border bg-amber-50 p-2 text-xs dark:bg-amber-950/30">
                <div className="mb-1 font-semibold">Compare mode ({selectedTeacherIds.length} guru)</div>
                <div className="mb-2 space-y-1">
                    {selectedTeacherIds.map((tid, idx) => {
                        const color = colorForTeacherIndex(idx);
                        const name = teacherNameById.get(tid) ?? `Guru #${tid}`;
                        return (
                            <div key={tid} className="flex items-center gap-1.5">
                                <span
                                    className={`inline-flex h-4 min-w-[16px] items-center justify-center rounded-sm px-0.5 text-[8px] font-bold leading-none ${color.badge} ${color.badgeText}`}
                                >
                                    {teacherInitials(name)}
                                </span>
                                <span className="truncate font-medium">{name}</span>
                            </div>
                        );
                    })}
                </div>
                <div className="text-muted-foreground">
                    Kolom kelas (union): <span className="font-medium text-foreground">{names || '—'}</span>
                </div>
                <div className="mt-2 text-[11px] text-muted-foreground">
                    Cell milik tiap guru ditandai outline dan badge berwarna. Klik cell terisi untuk menghapus.
                    Untuk assign baru, pilih hanya 1 guru.
                </div>
            </div>
        );
    }

    const onlyTeacherId = selectedTeacherIds[0];
    const assignmentProgress = pengampuList
        .filter((p) => p.teacher_id === onlyTeacherId)
        .map((p) => ({
            id: p.id,
            className: p.school_class?.name ?? `Kelas #${p.class_id}`,
            subjectName: p.subject?.name ?? `Mapel #${p.subject_id}`,
            progress: progressByPengampuId[p.id] ?? {
                allocated: 0,
                target: Math.max(1, p.target_jam_effective ?? p.target_jam ?? 1),
                remaining: Math.max(1, p.target_jam_effective ?? p.target_jam ?? 1),
                isFull: false,
            },
        }))
        .sort((a, b) => {
            if (a.progress.isFull !== b.progress.isFull) return a.progress.isFull ? 1 : -1;
            return b.progress.remaining - a.progress.remaining;
        });

    return (
        <div className="rounded-md border bg-amber-50 p-2 text-xs dark:bg-amber-950/30">
            <div className="mb-1 font-semibold">{onlyTeacherName}</div>
            <div className="text-muted-foreground">
                Kolom kelas: <span className="font-medium text-foreground">{names || '—'}</span>
            </div>
            <div className="mt-2 text-muted-foreground">
                Klik cell di salah satu kolom di atas. Jika guru mengampu lebih dari satu mapel di kelas yang sama,
                sistem akan meminta memilih mapel terlebih dahulu.
            </div>
            <div className="mt-1 text-[11px] text-muted-foreground">
                Cell merah dengan ikon segitiga = guru sudah terjadwal di kelas lain pada slot yang sama.
                Tombol <Move className="inline h-3 w-3" /> di pojok cell = pindahkan; bisa juga drag &amp; drop.
            </div>
            <div className="mt-2 space-y-1">
                {assignmentProgress.map((item) => (
                    <div
                        key={item.id}
                        className={
                            'rounded border px-2 py-1 text-[11px] ' +
                            (item.progress.isFull
                                ? 'border-emerald-400/40 bg-emerald-500/10'
                                : 'border-amber-400/40 bg-amber-500/10')
                        }
                    >
                        <div className="font-medium text-foreground">
                            {item.className} · {item.subjectName}
                        </div>
                        <div className="text-muted-foreground">
                            Terjadwal {item.progress.allocated}/{item.progress.target} jam
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function getCsrfToken(): string {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? (el.getAttribute('content') ?? '') : '';
}
