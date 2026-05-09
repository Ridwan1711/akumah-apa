import { TriangleAlert, X } from 'lucide-react';
import { forwardRef } from 'react';
import type { ScheduleMatrixCell, ScheduleMatrixPayload } from '@/types';
import { colorForSubject } from './subjectColors';

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
    selectedTeacherId: number | null;
    /** Kolom kelas yang di-highlight (semua kelas yang diampu guru terpilih). */
    highlightedClassIds: number[];
    visibleClassIds?: number[];
    onClickCell: (day: number, jamNo: number, classId: number) => void;
    onDeleteCell: (cell: ScheduleMatrixCell) => void;
    /** Bulk delete handlers (opsional). */
    onDeleteClassColumn?: (classId: number) => void;
    onDeleteDay?: (day: number) => void;
    onDeleteDayJam?: (day: number, jamNo: number) => void;
    /** Refs untuk auto-scroll ke kolom kelas pertama yang diampu. */
    scrollToClassId?: number | null;
};

const MatrixGrid = forwardRef<HTMLDivElement, Props>(function MatrixGrid(
    {
        matrix,
        selectedTeacherId,
        highlightedClassIds,
        visibleClassIds,
        onClickCell,
        onDeleteCell,
        onDeleteClassColumn,
        onDeleteDay,
        onDeleteDayJam,
        scrollToClassId,
    },
    ref,
) {
    const classes = visibleClassIds
        ? matrix.classes.filter((c) => visibleClassIds.includes(c.id))
        : matrix.classes;

    const slotByJam = new Map(matrix.slots.map((s) => [s.jam_no, s]));
    const jamRange = [...slotByJam.keys()].sort((a, b) => a - b);

    /**
     * Slot (day:jam) di mana guru terpilih sudah punya cell di kelas mana pun.
     * Berguna untuk:
     *  - menandai baris (sudah ada).
     *  - memberi peringatan visual pada cell kosong di kelas lain (kolom yang diampu)
     *    bahwa guru sudah terjadwal di slot tsb.
     */
    const teacherBusyDayJam = new Set<string>();
    if (selectedTeacherId != null) {
        for (const cell of Object.values(matrix.cells)) {
            if (cell.teacher_id === selectedTeacherId) {
                teacherBusyDayJam.add(`${cell.day}:${cell.jam_no}`);
            }
        }
    }

    const rowHasTeacher = (day: number, jamNo: number): boolean =>
        teacherBusyDayJam.has(`${day}:${jamNo}`);

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
                                selectedTeacherId != null && highlightedClassIds.includes(c.id);
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
                            const rowHighlight = rowHasTeacher(day, jamNo);
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
                                            selectedTeacherId != null && highlightedClassIds.includes(c.id);
                                        const teacherBusyHere = rowHighlight;
                                        const isOwnCell =
                                            cell != null &&
                                            selectedTeacherId != null &&
                                            cell.teacher_id === selectedTeacherId;
                                        const showBusyWarning =
                                            isHighlightedClass &&
                                            teacherBusyHere &&
                                            !isOwnCell;
                                        return (
                                            <Cell
                                                key={c.id}
                                                cell={cell}
                                                day={day}
                                                jamNo={jamNo}
                                                classId={c.id}
                                                selectedTeacherId={selectedTeacherId}
                                                onClick={() => onClickCell(day, jamNo, c.id)}
                                                onDelete={() => cell && onDeleteCell(cell)}
                                                showBusyWarning={showBusyWarning}
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
    selectedTeacherId: number | null;
    onClick: () => void;
    onDelete: () => void;
    showBusyWarning?: boolean;
};

function Cell({ cell, selectedTeacherId, onClick, onDelete, showBusyWarning }: CellProps) {
    const isTeacherMatch =
        cell && selectedTeacherId != null && cell.teacher_id === selectedTeacherId;
    const isCombined = !!cell?.combined_group_id;
    const subjectColor = cell ? colorForSubject(cell.subject_id) : null;

    const fillBg = subjectColor ? `${subjectColor.bg}` : '';
    const ringClass = isTeacherMatch
        ? 'ring-2 ring-amber-500 ring-inset'
        : showBusyWarning
          ? 'ring-2 ring-rose-400 ring-inset'
          : '';

    return (
        <td
            className={
                'group relative h-12 min-w-[90px] cursor-pointer border-b border-r px-1 py-1 text-center align-middle transition-colors hover:bg-primary/10 ' +
                fillBg +
                ' ' +
                ringClass
            }
            onClick={onClick}
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
                    {isCombined && (
                        <span
                            className="absolute right-0.5 top-0.5 rounded bg-emerald-500 px-1 text-[8px] font-bold text-white"
                            title="Kelas digabung"
                        >
                            G
                        </span>
                    )}
                    <button
                        type="button"
                        className="absolute bottom-0.5 right-0.5 hidden rounded bg-destructive/80 p-0.5 text-white hover:bg-destructive group-hover:block"
                        onClick={(e) => {
                            e.stopPropagation();
                            onDelete();
                        }}
                        title="Hapus"
                    >
                        <X className="h-2.5 w-2.5" />
                    </button>
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
