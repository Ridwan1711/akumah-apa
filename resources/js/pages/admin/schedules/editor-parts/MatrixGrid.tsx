import { X } from 'lucide-react';
import type { ScheduleMatrixCell, ScheduleMatrixPayload } from '@/types';

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
};

export default function MatrixGrid({
    matrix,
    selectedTeacherId,
    highlightedClassIds,
    visibleClassIds,
    onClickCell,
    onDeleteCell,
}: Props) {
    const classes = visibleClassIds
        ? matrix.classes.filter((c) => visibleClassIds.includes(c.id))
        : matrix.classes;

    const slotByJam = new Map(matrix.slots.map((s) => [s.jam_no, s]));

    const rowHasTeacher = (day: number, jamNo: number): boolean => {
        if (selectedTeacherId == null) return false;
        return matrix.classes.some((c) => {
            const key = `${day}:${jamNo}:${c.id}`;
            const cell = matrix.cells[key];
            return cell && cell.teacher_id === selectedTeacherId;
        });
    };

    return (
        <div className="relative overflow-auto rounded-md border">
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
                                    className={
                                        'min-w-[80px] border-b border-r px-2 py-2 text-center font-semibold ' +
                                        (isSelectedColumn
                                            ? 'bg-amber-50 ring-2 ring-amber-400 dark:bg-amber-950/30'
                                            : '')
                                    }
                                    title={c.name}
                                >
                                    {c.name}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {matrix.days.map((day) => {
                        const jamRange = [...slotByJam.keys()].sort((a, b) => a - b);
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
                                            className="sticky left-0 z-10 border-b border-r bg-background px-2 py-1 align-top font-semibold"
                                            rowSpan={jamRange.length}
                                        >
                                            {dayLabels[day] ?? `Hari ${day}`}
                                        </td>
                                    )}
                                    <td className="sticky left-20 z-10 border-b border-r bg-background px-2 py-1 text-center text-[10px]">
                                        <div className="font-medium">{jamNo}</div>
                                        {slot && (
                                            <div className="text-[9px] text-muted-foreground">
                                                {slot.time_start}-{slot.time_end}
                                            </div>
                                        )}
                                    </td>
                                    {classes.map((c) => {
                                        const key = `${day}:${jamNo}:${c.id}`;
                                        const cell = matrix.cells[key];
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
}

type CellProps = {
    cell?: ScheduleMatrixCell;
    day: number;
    jamNo: number;
    classId: number;
    selectedTeacherId: number | null;
    onClick: () => void;
    onDelete: () => void;
};

function Cell({ cell, selectedTeacherId, onClick, onDelete }: CellProps) {
    const isTeacherMatch =
        cell && selectedTeacherId != null && cell.teacher_id === selectedTeacherId;
    const isCombined = !!cell?.combined_group_id;

    const bg = isTeacherMatch
        ? 'bg-amber-200 dark:bg-amber-800/50'
        : cell
          ? 'bg-muted/40'
          : '';

    return (
        <td
            className={
                'group relative h-12 min-w-[80px] cursor-pointer border-b border-r px-1 py-1 text-center align-middle transition-colors hover:bg-primary/10 ' +
                bg
            }
            onClick={onClick}
            title={cell ? `${cell.teacher_name} - ${cell.subject_name}` : 'Kosong'}
        >
            {cell ? (
                <>
                    <div className="truncate font-medium">{cell.teacher_name ?? '-'}</div>
                    <div className="truncate text-[10px] text-muted-foreground">
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
            ) : (
                <span className="text-muted-foreground/40">—</span>
            )}
        </td>
    );
}
