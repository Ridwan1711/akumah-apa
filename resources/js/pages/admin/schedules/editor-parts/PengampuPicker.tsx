import { CheckCircle2, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ScheduleMatrixPengampu } from '@/types';

type TeacherGroupRow = {
    teacher_id: number;
    teacher_name: string;
    classCount: number;
    subjectCount: number;
    classNames: string[];
    pengampuIds: number[];
};

type Props = {
    pengampuList: ScheduleMatrixPengampu[];
    selectedTeacherId: number | null;
    onSelectTeacher: (teacherId: number | null) => void;
    /** Progress per pengampu (id → allocated/target). */
    progressByPengampuId?: Record<number, { allocated: number; target: number; isFull: boolean }>;
};

function buildTeacherRows(list: ScheduleMatrixPengampu[]): TeacherGroupRow[] {
    const byTeacher = new Map<
        number,
        {
            name: string;
            classes: Map<number, string>;
            subjects: Set<number>;
            pengampuIds: number[];
        }
    >();

    for (const p of list) {
        const tid = p.teacher_id;
        if (!byTeacher.has(tid)) {
            byTeacher.set(tid, {
                name: p.teacher?.name ?? `Guru #${tid}`,
                classes: new Map(),
                subjects: new Set(),
                pengampuIds: [],
            });
        }
        const g = byTeacher.get(tid)!;
        if (p.school_class?.id != null && p.school_class?.name) {
            g.classes.set(p.school_class.id, p.school_class.name);
        }
        g.subjects.add(p.subject_id);
        g.pengampuIds.push(p.id);
    }

    return [...byTeacher.entries()]
        .map(([teacher_id, g]) => ({
            teacher_id,
            teacher_name: g.name,
            classCount: g.classes.size,
            subjectCount: g.subjects.size,
            classNames: [...g.classes.values()].sort(),
            pengampuIds: g.pengampuIds,
        }))
        .sort((a, b) => a.teacher_name.localeCompare(b.teacher_name, 'id'));
}

function rowMatchesQuery(
    row: TeacherGroupRow,
    list: ScheduleMatrixPengampu[],
    q: string,
): boolean {
    if (!q) return true;
    const hay = [
        row.teacher_name,
        ...row.classNames,
        ...list
            .filter((p) => p.teacher_id === row.teacher_id)
            .map((p) => p.subject?.name)
            .filter(Boolean),
    ]
        .join(' ')
        .toLowerCase();
    return hay.includes(q);
}

type TeacherTotals = {
    allocated: number;
    target: number;
    pct: number;
    isFull: boolean;
};

function computeTeacherTotals(
    row: TeacherGroupRow,
    progressByPengampuId: Props['progressByPengampuId'],
): TeacherTotals {
    if (!progressByPengampuId) {
        return { allocated: 0, target: 0, pct: 0, isFull: false };
    }
    let allocated = 0;
    let target = 0;
    for (const id of row.pengampuIds) {
        const prog = progressByPengampuId[id];
        if (!prog) continue;
        allocated += prog.allocated;
        target += prog.target;
    }
    const pct = target > 0 ? Math.round((allocated / target) * 100) : 0;
    return { allocated, target, pct, isFull: target > 0 && allocated >= target };
}

export default function PengampuPicker({
    pengampuList,
    selectedTeacherId,
    onSelectTeacher,
    progressByPengampuId,
}: Props) {
    const [query, setQuery] = useState('');
    const [sortMode, setSortMode] = useState<'name' | 'progress'>('progress');

    const rows = useMemo(() => buildTeacherRows(pengampuList), [pengampuList]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        const base = !q ? rows : rows.filter((r) => rowMatchesQuery(r, pengampuList, q));

        if (sortMode === 'name') {
            return [...base].sort((a, b) => a.teacher_name.localeCompare(b.teacher_name, 'id'));
        }
        return [...base].sort((a, b) => {
            const at = computeTeacherTotals(a, progressByPengampuId);
            const bt = computeTeacherTotals(b, progressByPengampuId);
            if (at.isFull !== bt.isFull) return at.isFull ? 1 : -1;
            const remA = at.target - at.allocated;
            const remB = bt.target - bt.allocated;
            if (remA !== remB) return remB - remA;
            return a.teacher_name.localeCompare(b.teacher_name, 'id');
        });
    }, [rows, pengampuList, query, sortMode, progressByPengampuId]);

    return (
        <div className="flex h-full flex-col gap-2">
            <div className="relative">
                <Search className="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder="Cari guru / kelas / mapel"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    className="pl-8"
                />
            </div>
            <div className="flex items-center gap-1.5">
                <span className="text-[11px] text-muted-foreground">Urut:</span>
                <button
                    type="button"
                    className={
                        'rounded border px-1.5 py-0.5 text-[11px] ' +
                        (sortMode === 'progress'
                            ? 'border-primary bg-primary/10 font-medium'
                            : 'hover:bg-muted')
                    }
                    onClick={() => setSortMode('progress')}
                >
                    Belum penuh
                </button>
                <button
                    type="button"
                    className={
                        'rounded border px-1.5 py-0.5 text-[11px] ' +
                        (sortMode === 'name'
                            ? 'border-primary bg-primary/10 font-medium'
                            : 'hover:bg-muted')
                    }
                    onClick={() => setSortMode('name')}
                >
                    Nama
                </button>
            </div>
            {selectedTeacherId != null && (
                <Button type="button" variant="outline" size="sm" onClick={() => onSelectTeacher(null)}>
                    Batalkan pilihan
                </Button>
            )}
            <div className="flex-1 overflow-auto rounded-md border">
                {filtered.length === 0 ? (
                    <div className="p-3 text-center text-sm text-muted-foreground">
                        Tidak ada guru yang cocok.
                    </div>
                ) : (
                    <ul className="divide-y">
                        {filtered.map((row) => {
                            const active = row.teacher_id === selectedTeacherId;
                            const totals = computeTeacherTotals(row, progressByPengampuId);
                            const subtitle =
                                row.classCount <= 3
                                    ? row.classNames.join(', ')
                                    : `${row.classNames.slice(0, 2).join(', ')} +${row.classCount - 2} kelas`;
                            return (
                                <li key={row.teacher_id}>
                                    <button
                                        type="button"
                                        className={
                                            'w-full px-3 py-2 text-left text-sm transition-colors hover:bg-muted ' +
                                            (active
                                                ? 'bg-amber-100 hover:bg-amber-100 dark:bg-amber-900/40'
                                                : '')
                                        }
                                        onClick={() => onSelectTeacher(row.teacher_id)}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="font-medium">{row.teacher_name}</div>
                                            {totals.target > 0 && totals.isFull && (
                                                <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.classCount} kelas
                                            {row.subjectCount > 1 ? ` · ${row.subjectCount} mapel` : ''}
                                            {totals.target > 0 && (
                                                <>
                                                    {' '}
                                                    · {totals.allocated}/{totals.target} jam
                                                </>
                                            )}
                                        </div>
                                        {totals.target > 0 && (
                                            <div className="mt-1 h-1 w-full overflow-hidden rounded bg-muted">
                                                <div
                                                    className={
                                                        'h-full transition-all ' +
                                                        (totals.isFull
                                                            ? 'bg-emerald-500'
                                                            : totals.pct >= 50
                                                              ? 'bg-amber-500'
                                                              : 'bg-rose-500')
                                                    }
                                                    style={{ width: `${Math.min(100, totals.pct)}%` }}
                                                />
                                            </div>
                                        )}
                                        <div className="mt-0.5 line-clamp-2 text-[11px] text-muted-foreground/90">
                                            {subtitle}
                                        </div>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
