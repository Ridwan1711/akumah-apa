import { ChevronDown, ChevronRight, Palette } from 'lucide-react';
import { useState } from 'react';
import { colorForSubject } from './subjectColors';

type Props = {
    subjects: Array<{ subject_id: number; subject_name: string }>;
};

export default function ColorLegend({ subjects }: Props) {
    const [open, setOpen] = useState(false);

    if (subjects.length === 0) return null;

    return (
        <div className="rounded-md border bg-background text-xs">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-1.5 px-2 py-1.5 text-left font-medium hover:bg-muted/50"
            >
                {open ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                <Palette className="h-3 w-3" />
                Legend warna mapel ({subjects.length})
            </button>
            {open && (
                <div className="flex flex-wrap gap-1.5 border-t p-2">
                    {subjects.map((s) => {
                        const color = colorForSubject(s.subject_id);
                        return (
                            <span
                                key={s.subject_id}
                                className={`inline-flex items-center gap-1.5 rounded border px-1.5 py-0.5 ${color.border} ${color.bg}`}
                                title={s.subject_name}
                            >
                                <span className={`h-2 w-2 rounded-full ${color.dot}`} />
                                <span className="truncate text-[11px]">{s.subject_name}</span>
                            </span>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
