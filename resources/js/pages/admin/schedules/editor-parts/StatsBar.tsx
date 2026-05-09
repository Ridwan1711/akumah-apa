import { CheckCircle2, Grid3x3, TriangleAlert, Users } from 'lucide-react';

type Props = {
    totalSlots: number;
    filledCount: number;
    teacherClashes: number;
    pengampuTotal: number;
    pengampuFulfilled: number;
};

export default function StatsBar({
    totalSlots,
    filledCount,
    teacherClashes,
    pengampuTotal,
    pengampuFulfilled,
}: Props) {
    const fillPct = totalSlots > 0 ? Math.round((filledCount / totalSlots) * 100) : 0;
    const targetPct = pengampuTotal > 0 ? Math.round((pengampuFulfilled / pengampuTotal) * 100) : 0;

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <Tile
                tone="blue"
                icon={<Grid3x3 className="h-4 w-4" />}
                label="Slot Terisi"
                value={`${filledCount} / ${totalSlots}`}
                hint={`${fillPct}% kapasitas grid`}
            />
            <Tile
                tone="emerald"
                icon={<CheckCircle2 className="h-4 w-4" />}
                label="Target Pengampu"
                value={`${pengampuFulfilled} / ${pengampuTotal}`}
                hint={`${targetPct}% pengampu memenuhi target`}
            />
            <Tile
                tone={teacherClashes > 0 ? 'red' : 'muted'}
                icon={<TriangleAlert className="h-4 w-4" />}
                label="Bentrok Guru"
                value={teacherClashes.toString()}
                hint={teacherClashes > 0 ? 'slot dengan guru di kelas ganda' : 'tidak ada bentrok'}
            />
            <Tile
                tone="amber"
                icon={<Users className="h-4 w-4" />}
                label="Persentase Penjadwalan"
                value={`${fillPct}%`}
                hint={`${pengampuTotal - pengampuFulfilled} pengampu belum penuh`}
            />
        </div>
    );
}

type TileProps = {
    icon: React.ReactNode;
    label: string;
    value: string;
    hint: string;
    tone: 'blue' | 'emerald' | 'red' | 'amber' | 'muted';
};

function Tile({ icon, label, value, hint, tone }: TileProps) {
    const toneClass: Record<TileProps['tone'], string> = {
        blue: 'border-sky-300/50 bg-sky-50/60 dark:border-sky-700/40 dark:bg-sky-950/30',
        emerald:
            'border-emerald-300/50 bg-emerald-50/60 dark:border-emerald-700/40 dark:bg-emerald-950/30',
        red: 'border-rose-300/60 bg-rose-50/60 dark:border-rose-700/40 dark:bg-rose-950/30',
        amber: 'border-amber-300/60 bg-amber-50/60 dark:border-amber-700/40 dark:bg-amber-950/30',
        muted: 'border-border bg-muted/30',
    };
    const iconTone: Record<TileProps['tone'], string> = {
        blue: 'text-sky-600 dark:text-sky-400',
        emerald: 'text-emerald-600 dark:text-emerald-400',
        red: 'text-rose-600 dark:text-rose-400',
        amber: 'text-amber-600 dark:text-amber-400',
        muted: 'text-muted-foreground',
    };

    return (
        <div className={`rounded-md border p-2 text-xs ${toneClass[tone]}`}>
            <div className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
                <span className={iconTone[tone]}>{icon}</span>
                {label}
            </div>
            <div className="mt-0.5 text-base font-semibold leading-tight text-foreground">{value}</div>
            <div className="mt-0.5 text-[10px] text-muted-foreground">{hint}</div>
        </div>
    );
}
