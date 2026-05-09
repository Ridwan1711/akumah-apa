import { Move, TriangleAlert, X } from 'lucide-react';
import { forwardRef } from 'react';
import type { ScheduleMatrixCell, ScheduleMatrixPayload } from '@/types';
import { colorForSubject } from './subjectColors';
import { colorForTeacherIndex, teacherInitials } from './teacherColors';

const dayLabels: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Ahad',
};

type Props = {
    matrix: ScheduleMatrixPayload;
    /** Daftar guru terpilih, urut sesuai pemilihan (untuk pemetaan warna). */
    selectedTeacherIds: number[];
    /** Kolom kelas yang di-highlight (semua kelas yang diampu guru terpilih). */
    highlightedClassIds: number[];
    visibleClassIds?: number[];
    onClickCell: (day: number, jamNo: number, classId: number) => void;
    onDeleteCell: (cell: ScheduleMatrixCell) => void;
    onMoveStart?: (cell: ScheduleMatrixCell) => void;
    onMoveDrop?: (sourceCellScheduleId: number, targetClassId: number, targetDay: number, targetJamNo: number) => void;
    /** Bulk delete handlers (opsional). */
    onDeleteClassColumn?: (classId: number) => void;
    onDeleteDay?: (day: number) => void;
    onDeleteDayJam?: (day: number, jamNo: number) => void;
    /** Cell yang sedang berada dalam mode pick-then-place. */
    movingCellId?: number | null;
};

const MatrixGrid = forwardRef<HTMLDivElement, Props>(function MatrixGrid(
    {
        matrix,
        selectedTeacherIds,
        highlightedClassIds,
        visibleClassIds,
        onClickCell,
        onDeleteCell,
        onMoveStart,
        onMoveDrop,
        onDeleteClassColumn,
        onDeleteDay,
        onDeleteDayJam,
        movingCellId,
    },
    ref,
) {
    const classes = visibleClassIds
        ? matrix.classes.filter((c) => visibleClassIds.includes(c.id))
        : matrix.classes;

    const slotByJam = new Map(matrix.slots.map((s) => [s.jam_no, s]));
    const jamRange = [...slotByJam.keys()].sort((a, b) => a - b);

    const teacherIndexMap = new Map<number, number>();
    selectedTeacherIds.forEach((tid, idx) => teacherIndexMap.set(tid, idx));
    const isMultiSelect = selectedTeacherIds.length > 1;
    const isSingleSelect = selectedTeacherIds.length === 1;
    const onlyTeacherId = isSingleSelect ? selectedTeacherIds[0] : null;

    /**
     * Slot (day:jam) di mana satu guru terpilih sudah punya cell di kelas mana pun.
     * Berguna untuk menandai baris dan memberi peringatan visual cell kosong.
     * Saat multi-select kita pakai map per teacher_id agar peringatan akurat.
     */
    const teacherBusyMap = new Map<number, Set<string>>();
    if (selectedTeacherIds.length > 0) {
        for (const tid of selectedTeacherIds) {
            teacherBusyMap.set(tid, new Set<string>());
        }
        for (const cell of Object.values(matrix.cells)) {
            const set = teacherBusyMap.get(cell.teacher_id);
            if (set) set.add(`${cell.day}:${cell.jam_no}`);
        }
    }

    const rowHasAnyTeacher = (day: number, jamNo: number): boolean => {
        const k = `${day}:${jamNo}`;
        for (const set of teacherBusyMap.values()) {
            if (set.has(k)) return true;
        }
        return false;
    };

    return (
        <div ref={ref} className="relative overflow-auto rounded-md border">
            <table className="border-collapse text-xs">
                <thead className="sticky top-0 z-20 bg-background shadow-sm">
                    <tr>
                        <th className="sticky left-0 top-0 z-30 w-20 border-b border-r bg-background px-2 py-2 text-left">
                            Hari
                        </th>
                        <th className="sticky left-20 top-0 z-30 w-16 border-b border-r bg-background px-2 py-2 text-center">
                            Jam
                        </th>
                        {classes.map((c) => {
                            const isSelectedColumn =
                                selectedTeacherIds.length > 0 && highlightedClassIds.includes(c.id);
                            return (
                                <th
                                    key={c.id}
                                    data-class-col={c.id}
                                    className={
                                        'group/colhead relative min-w-[90px] border-b border-r px-2 py-2 text-center font-semibold ' +
                                        (isSelectedColumn
                                            ? 'bg-amber-50 ring-2 ring-amber-400 dark:bg-amber-950/30'
                                            : '')
                                    }
                                    title={c.name}
                                >
                                    {c.name}
                                    {onDeleteClassColumn && (
                                        <button
                                            type="button"
                                            className="absolute right-0.5 top-0.5 hidden rounded p-0.5 text-rose-600 hover:bg-rose-100 group-hover/colhead:inline-flex dark:hover:bg-rose-950/40"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onDeleteClassColumn(c.id);
                                            }}
                                            title={`Hapus semua jadwal di ${c.name}`}
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {matrix.days.map((day) => {
                        return jamRange.map((jamNo, idx) => {
                            const slot = slotByJam.get(jamNo);
                            const rowHighlight = rowHasAnyTeacher(day, jamNo);
                            return (
                                <tr
                                    key={`${day}-${jamNo}`}
                                    className={
                                        rowHighlight
                                            ? 'bg-amber-50/60 dark:bg-amber-950/20'
                                            : ''
                                    }
                                >
                                    {idx === 0 && (
                                        <td
                                            className="group/dayhead sticky left-0 z-10 border-b border-r bg-background px-2 py-1 align-top font-semibold"
                                            rowSpan={jamRange.length}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span>{dayLabels[day] ?? `Hari ${day}`}</span>
                                                {onDeleteDay && (
                                                    <button
                                                        type="button"
                                                        className="hidden rounded p-0.5 text-rose-600 hover:bg-rose-100 group-hover/dayhead:inline-flex dark:hover:bg-rose-950/40"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            onDeleteDay(day);
                                                        }}
                                                        title={`Hapus semua jadwal di ${dayLabels[day] ?? `Hari ${day}`}`}
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                    <td className="group/jamhead sticky left-20 z-10 border-b border-r bg-background px-2 py-1 text-center text-[10px]">
                                        <div className="flex items-center justify-center gap-1">
                                            <span className="font-medium">{jamNo}</span>
                                            {onDeleteDayJam && (
                                                <button
                                                    type="button"
                                                    className="hidden rounded p-0.5 text-rose-600 hover:bg-rose-100 group-hover/jamhead:inline-flex dark:hover:bg-rose-950/40"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        onDeleteDayJam(day, jamNo);
                                                    }}
                                                    title={`Hapus semua jadwal di ${dayLabels[day] ?? `Hari ${day}`} jam ${jamNo}`}
                                                >
                                                    <X className="h-3 w-3" />
                                                </button>
                                            )}
                                        </div>
                                        {slot && (
                                            <div className="text-[9px] text-muted-foreground">
                                                {slot.time_start}-{slot.time_end}
                                            </div>
                                        )}
                                    </td>
                                    {classes.map((c) => {
                                        const key = `${day}:${jamNo}:${c.id}`;
                                        const cell = matrix.cells[key];
                                        const isHighlightedClass =
                                            selectedTeacherIds.length > 0 && highlightedClassIds.includes(c.id);
                                        const cellTeacherIdx =
                                            cell != null ? teacherIndexMap.get(cell.teacher_id) ?? -1 : -1;
                                        const isOwnCell = cellTeacherIdx >= 0;
                                        const ownTeacherBusy =
                                            isSingleSelect &&
                                            isHighlightedClass &&
                                            (teacherBusyMap.get(onlyTeacherId!)?.has(`${day}:${jamNo}`) ?? false);
                                        const showBusyWarning =
                                            isSingleSelect &&
                                            isHighlightedClass &&
                                            ownTeacherBusy &&
                                            !isOwnCell &&
                                            cell == null;
                                        const isMoving = movingCellId != null && cell?.schedule_id === movingCellId;
                                        return (
                                            <Cell
                                                key={c.id}
                                                cell={cell}
                                                day={day}
                                                jamNo={jamNo}
                                                classId={c.id}
                                                onClick={() => onClickCell(day, jamNo, c.id)}
                                                onDelete={() => cell && onDeleteCell(cell)}
                                                onMoveStart={onMoveStart && cell ? () => onMoveStart(cell) : undefined}
                                                onDragStartCell={
                                                    onMoveStart && cell
                                                        ? () => onMoveStart(cell)
                                                        : undefined
                                                }
                                                onDropTarget={
                                                    onMoveDrop
                                                        ? (sourceId) =>
                                                              onMoveDrop(sourceId, c.id, day, jamNo)
                                                        : undefined
                                                }
                                                showBusyWarning={showBusyWarning}
                                                ownTeacherIndex={cellTeacherIdx}
                                                isMoving={isMoving}
                                                isMultiSelect={isMultiSelect}
                                            />
                                        );
                                    })}
                                </tr>
                            );
                        });
                    })}
                </tbody>
            </table>
        </div>
    );
});

export default MatrixGrid;

type CellProps = {
    cell?: ScheduleMatrixCell;
    day: number;
    jamNo: number;
    classId: number;
    onClick: () => void;
    onDelete: () => void;
    onMoveStart?: () => void;
    onDragStartCell?: () => void;
    onDropTarget?: (sourceCellScheduleId: number) => void;
    showBusyWarning?: boolean;
    /** Index pada selectedTeacherIds; -1 berarti tidak terpilih. */
    ownTeacherIndex: number;
    isMoving: boolean;
    isMultiSelect: boolean;
};

function Cell({
    cell,
    onClick,
    onDelete,
    onMoveStart,
    onDragStartCell,
    onDropTarget,
    showBusyWarning,
    ownTeacherIndex,
    isMoving,
    isMultiSelect,
}: CellProps) {
    const isOwnCell = ownTeacherIndex >= 0;
    const teacherColor = isOwnCell ? colorForTeacherIndex(ownTeacherIndex) : null;
    const isCombined = !!cell?.combined_group_id;
    const subjectColor = cell ? colorForSubject(cell.subject_id) : null;

    const fillBg = subjectColor ? `${subjectColor.bg}` : '';
    const ringClass = teacherColor
        ? `ring-2 ${teacherColor.ring} ring-inset`
        : showBusyWarning
          ? 'ring-2 ring-rose-400 ring-inset'
          : '';
    const movingClass = isMoving ? 'opacity-40 outline-dashed outline-2 outline-primary' : '';

    return (
        <td
            className={
                'group relative h-12 min-w-[90px] cursor-pointer border-b border-r px-1 py-1 text-center align-middle transition-colors hover:bg-primary/10 ' +
                fillBg +
                ' ' +
                ringClass +
                ' ' +
                movingClass
            }
            onClick={onClick}
            draggable={!!cell && !!onDragStartCell}
            onDragStart={(e) => {
                if (!cell || !onDragStartCell) return;
                e.dataTransfer.setData('text/x-schedule-cell', String(cell.schedule_id));
                e.dataTransfer.effectAllowed = 'move';
                onDragStartCell();
            }}
            onDragOver={(e) => {
                if (!onDropTarget) return;
                if (!e.dataTransfer.types.includes('text/x-schedule-cell')) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            }}
            onDrop={(e) => {
                if (!onDropTarget) return;
                const raw = e.dataTransfer.getData('text/x-schedule-cell');
                const sid = Number(raw);
                if (!sid) return;
                e.preventDefault();
                onDropTarget(sid);
            }}
            title={
                cell
                    ? `${cell.teacher_name ?? '-'} · ${cell.subject_name ?? '-'}`
                    : showBusyWarning
                      ? 'Guru sudah terjadwal di kelas lain pada slot ini'
                      : 'Kosong — klik untuk menempatkan'
            }
        >
            {cell ? (
                <>
                    <div className="truncate font-medium leading-tight">{cell.teacher_name ?? '-'}</div>
                    <div className="truncate text-[10px] leading-tight text-muted-foreground">
                        {cell.subject_name ?? '-'}
                    </div>
                    {teacherColor && (
                        <span
                            className={`absolute left-0.5 top-0.5 inline-flex h-4 min-w-[16px] items-center justify-center rounded-sm px-0.5 text-[8px] font-bold leading-none ${teacherColor.badge} ${teacherColor.badgeText}`}
                            title={cell.teacher_name ?? ''}
                        >
                            {teacherInitials(cell.teacher_name ?? '?')}
                        </span>
                    )}
                    {isCombined && (
                        <span
                            className="absolute right-0.5 top-0.5 rounded bg-emerald-500 px-1 text-[8px] font-bold text-white"
                            title="Kelas digabung"
                        >
                            G
                        </span>
                    )}
                    <div className="absolute bottom-0.5 right-0.5 hidden gap-0.5 group-hover:flex">
                        {onMoveStart && !isMultiSelect && (
                            <button
                                type="button"
                                className="rounded bg-primary/80 p-0.5 text-white hover:bg-primary"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onMoveStart();
                                }}
                                title="Pindahkan cell"
                            >
                                <Move className="h-2.5 w-2.5" />
                            </button>
                        )}
                        <button
                            type="button"
                            className="rounded bg-destructive/80 p-0.5 text-white hover:bg-destructive"
                            onClick={(e) => {
                                e.stopPropagation();
                                onDelete();
                            }}
                            title="Hapus"
                        >
                            <X className="h-2.5 w-2.5" />
                        </button>
                    </div>
                </>
            ) : showBusyWarning ? (
                <span className="inline-flex items-center gap-0.5 text-[10px] text-rose-600 dark:text-rose-400">
                    <TriangleAlert className="h-3 w-3" />
                    bentrok
                </span>
            ) : (
                <span className="text-muted-foreground/40">—</span>
            )}
        </td>
    );
}
