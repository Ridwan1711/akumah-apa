import { Search } from 'lucide-react';
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
};

type Props = {
    pengampuList: ScheduleMatrixPengampu[];
    selectedTeacherId: number | null;
    onSelectTeacher: (teacherId: number | null) => void;
};

function buildTeacherRows(list: ScheduleMatrixPengampu[]): TeacherGroupRow[] {
    const byTeacher = new Map<
        number,
        { name: string; classes: Map<number, string>; subjects: Set<number> }
    >();

    for (const p of list) {
        const tid = p.teacher_id;
        if (!byTeacher.has(tid)) {
            byTeacher.set(tid, {
                name: p.teacher?.name ?? `Guru #${tid}`,
                classes: new Map(),
                subjects: new Set(),
            });
        }
        const g = byTeacher.get(tid)!;
        if (p.school_class?.id != null && p.school_class?.name) {
            g.classes.set(p.school_class.id, p.school_class.name);
        }
        g.subjects.add(p.subject_id);
    }

    return [...byTeacher.entries()]
        .map(([teacher_id, g]) => ({
            teacher_id,
            teacher_name: g.name,
            classCount: g.classes.size,
            subjectCount: g.subjects.size,
            classNames: [...g.classes.values()].sort(),
        }))
        .sort((a, b) => a.teacher_name.localeCompare(b.teacher_name, 'id'));
}

function rowMatchesQuery(row: TeacherGroupRow, list: ScheduleMatrixPengampu[], q: string): boolean {
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

export default function PengampuPicker({
    pengampuList,
    selectedTeacherId,
    onSelectTeacher,
}: Props) {
    const [query, setQuery] = useState('');

    const rows = useMemo(() => buildTeacherRows(pengampuList), [pengampuList]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((r) => rowMatchesQuery(r, pengampuList, q));
    }, [rows, pengampuList, query]);

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
                                        <div className="font-medium">{row.teacher_name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.classCount} kelas
                                            {row.subjectCount > 1 ? ` · ${row.subjectCount} mapel` : ''}
                                        </div>
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
