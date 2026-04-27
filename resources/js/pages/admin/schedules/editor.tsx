import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
import ConflictDialog, { type ConflictAction } from './editor-parts/ConflictDialog';
import MatrixGrid from './editor-parts/MatrixGrid';
import PengampuPicker from './editor-parts/PengampuPicker';
import SubjectPickDialog from './editor-parts/SubjectPickDialog';
import TimeSlotSettingsDialog from './editor-parts/TimeSlotSettingsDialog';

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

const dayLabels: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Ahad',
};

export default function ScheduleMatrixEditor({ scheduleSet, matrix, pengampuList }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets' },
        { title: scheduleSet.name, href: `/admin/schedule-sets/${scheduleSet.id}/editor` },
    ];

    const [selectedTeacherId, setSelectedTeacherId] = useState<number | null>(null);
    const [subjectPick, setSubjectPick] = useState<SubjectPickState | null>(null);
    const [conflict, setConflict] = useState<ScheduleConflictResponse | null>(null);
    const [pendingAssign, setPendingAssign] = useState<{
        pengampuId: number;
        day: number;
        jamNo: number;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const [settingsOpen, setSettingsOpen] = useState(false);

    useEffect(() => {
        if (matrix.slots.length === 0) {
            setSettingsOpen(true);
        }
    }, [matrix.slots.length]);

    const teacherClassIds = useMemo(() => {
        if (selectedTeacherId == null) return null;
        const ids = new Set<number>();
        for (const p of pengampuList) {
            if (p.teacher_id === selectedTeacherId) {
                ids.add(p.class_id);
            }
        }
        return [...ids];
    }, [selectedTeacherId, pengampuList]);

    const selectedTeacherName = useMemo(() => {
        if (selectedTeacherId == null) return null;
        const p = pengampuList.find((x) => x.teacher_id === selectedTeacherId);
        return p?.teacher?.name ?? `Guru #${selectedTeacherId}`;
    }, [selectedTeacherId, pengampuList]);

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
            const target = Math.max(1, p.target_jam ?? 1);
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

    const pengampuForClass = useCallback(
        (classId: number): ScheduleMatrixPengampu[] => {
            if (selectedTeacherId == null) return [];
            return pengampuList.filter(
                (p) => p.teacher_id === selectedTeacherId && p.class_id === classId,
            );
        },
        [selectedTeacherId, pengampuList],
    );

    function postAssign(
        action: ConflictAction,
        target: { pengampuId: number; day: number; jamNo: number },
    ) {
        setBusy(true);
        router.post(
            `/admin/schedule-sets/${scheduleSet.id}/cells`,
            {
                pengampu_id: target.pengampuId,
                day: target.day,
                jam_no: target.jamNo,
                action,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setConflict(null);
                    setPendingAssign(null);
                },
            },
        );
    }

    async function runPreflightAndAssign(pengampuId: number, day: number, jamNo: number) {
        setBusy(true);
        try {
            const res = await fetch(`/admin/schedule-sets/${scheduleSet.id}/cells/preflight`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    pengampu_id: pengampuId,
                    day,
                    jam_no: jamNo,
                }),
            });

            if (!res.ok) {
                throw new Error('Preflight gagal');
            }

            const data = (await res.json()) as ScheduleConflictResponse;

            setBusy(false);
            if (data.type === 'target_reached') {
                alert(
                    `Target jam sudah terpenuhi (${data.allocation ?? 0}/${data.target_jam ?? 0}). Hapus atau pindahkan slot lain jika ingin mengubah.`,
                );
                return;
            }
            if (data.type === 'none') {
                postAssign('assign', { pengampuId, day, jamNo });
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
        if (selectedTeacherId == null) {
            const cellKey = `${day}:${jamNo}:${classId}`;
            const cell = matrix.cells[cellKey];
            if (cell && confirm(`Hapus cell ${cell.teacher_name} - ${cell.subject_name}?`)) {
                handleDeleteCell(cell);
            }
            return;
        }

        if (teacherClassIds && !teacherClassIds.includes(classId)) {
            return;
        }

        const matches = pengampuForClass(classId);
        if (matches.length === 0) {
            return;
        }

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

    function confirmConflict(action: ConflictAction) {
        if (!pendingAssign) return;
        postAssign(action, pendingAssign);
    }

    function handleDeleteCell(cell: ScheduleMatrixCell) {
        const isCombined = !!cell.combined_group_id;
        let deleteGroup = false;
        if (isCombined) {
            const ans = window.confirm(
                'Cell ini bagian dari kelas digabung. Tekan OK untuk menghapus SELURUH grup gabungan, atau Batal untuk menghapus cell ini saja.',
            );
            deleteGroup = ans;
        }

        router.delete(
            `/admin/schedule-sets/${scheduleSet.id}/cells/${cell.schedule_id}` +
                (deleteGroup ? '?delete_group=1' : ''),
            { preserveScroll: true },
        );
    }

    async function onSubjectPicked(pengampu: ScheduleMatrixPengampu) {
        if (!subjectPick) return;
        const { day, jamNo } = subjectPick;
        setSubjectPick(null);
        await runPreflightAndAssign(pengampu.id, day, jamNo);
    }

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
                                scheduleSet.period?.name
                                    ? `Periode ${scheduleSet.period.name}`
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
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setSettingsOpen(true)}
                        >
                            <Clock className="mr-1 h-4 w-4" />
                            Pengaturan jam ({scheduleSet.jam_count} jam × {scheduleSet.day_count} hari)
                        </Button>
                    </div>
                </div>

                <div className="grid flex-1 grid-cols-[260px_1fr] gap-3 overflow-hidden">
                    <aside className="flex flex-col gap-2 overflow-hidden">
                        <div className="text-sm font-semibold">Guru (pengampu)</div>
                        <PengampuPicker
                            pengampuList={pengampuList}
                            selectedTeacherId={selectedTeacherId}
                            onSelectTeacher={setSelectedTeacherId}
                        />
                        <InstructionPanel
                            selectedTeacherId={selectedTeacherId}
                            teacherName={selectedTeacherName}
                            teacherClassIds={teacherClassIds}
                            matrix={matrix}
                            pengampuList={pengampuList}
                            progressByPengampuId={progressByPengampuId}
                        />
                    </aside>
                    <main className="flex-1 overflow-hidden">
                        {matrix.slots.length === 0 ? (
                            <div className="rounded-md border p-8 text-center text-sm text-muted-foreground">
                                Slot jam belum dikonfigurasi. Klik tombol "Pengaturan jam" di atas untuk mengisi
                                waktu tiap jam ke-N.
                            </div>
                        ) : (
                            <MatrixGrid
                                matrix={matrix}
                                selectedTeacherId={selectedTeacherId}
                                highlightedClassIds={teacherClassIds ?? []}
                                visibleClassIds={teacherClassIds ?? undefined}
                                onClickCell={handleClickCell}
                                onDeleteCell={handleDeleteCell}
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
    selectedTeacherId,
    teacherName,
    teacherClassIds,
    matrix,
    pengampuList,
    progressByPengampuId,
}: {
    selectedTeacherId: number | null;
    teacherName: string | null;
    teacherClassIds: number[] | null;
    matrix: ScheduleMatrixPayload;
    pengampuList: ScheduleMatrixPengampu[];
    progressByPengampuId: Record<number, PengampuProgress>;
}) {
    if (selectedTeacherId == null) {
        return (
            <div className="rounded-md border bg-muted/30 p-2 text-xs text-muted-foreground">
                Pilih guru di daftar, lalu matrix hanya menampilkan kelas yang diampunya. Klik cell pada kolom
                kelas yang sesuai untuk menempatkan jadwal. Klik cell yang sudah terisi (tanpa guru terpilih)
                untuk menghapus.
            </div>
        );
    }
    const names =
        teacherClassIds
            ?.map((id) => matrix.classes.find((c) => c.id === id)?.name)
            .filter(Boolean)
            .join(', ') ?? '';
    const assignmentProgress = pengampuList
        .filter((p) => p.teacher_id === selectedTeacherId)
        .map((p) => ({
            id: p.id,
            className: p.school_class?.name ?? `Kelas #${p.class_id}`,
            subjectName: p.subject?.name ?? `Mapel #${p.subject_id}`,
            progress: progressByPengampuId[p.id] ?? {
                allocated: 0,
                target: Math.max(1, p.target_jam ?? 1),
                remaining: Math.max(1, p.target_jam ?? 1),
                isFull: false,
            },
        }))
        .sort((a, b) => {
            if (a.progress.isFull !== b.progress.isFull) {
                return a.progress.isFull ? 1 : -1;
            }
            return b.progress.remaining - a.progress.remaining;
        });

    return (
        <div className="rounded-md border bg-amber-50 p-2 text-xs dark:bg-amber-950/30">
            <div className="mb-1 font-semibold">{teacherName}</div>
            <div className="text-muted-foreground">
                Kolom kelas: <span className="font-medium text-foreground">{names || '—'}</span>
            </div>
            <div className="mt-2 text-muted-foreground">
                Klik cell di salah satu kolom di atas. Jika guru mengampu lebih dari satu mapel di kelas yang sama,
                sistem akan meminta memilih mapel terlebih dahulu.
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
