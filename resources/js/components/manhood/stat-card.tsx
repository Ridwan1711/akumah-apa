import type { ElementType } from 'react';
import { cn } from '@/lib/utils';

const themes = {
    emerald: 'border-emerald-200/80 bg-emerald-50 text-emerald-600 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400',
    blue: 'border-blue-200/80 bg-blue-50 text-blue-600 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-400',
    violet: 'border-violet-200/80 bg-violet-50 text-violet-600 dark:border-violet-900/50 dark:bg-violet-950/40 dark:text-violet-400',
    amber: 'border-amber-200/80 bg-amber-50 text-amber-600 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400',
    rose: 'border-rose-200/80 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400',
    slate: 'border-border bg-muted/50 text-muted-foreground',
} as const;

export type ManhoodStatTheme = keyof typeof themes;

type Props = {
    title: string;
    value: number | string;
    icon: ElementType;
    theme?: ManhoodStatTheme;
    trend?: number | null;
    trendLabel?: string;
    className?: string;
};

export function ManhoodStatCard({
    title,
    value,
    icon: Icon,
    theme = 'emerald',
    trend = null,
    trendLabel = '',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'group relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:shadow-md dark:shadow-none',
                className,
            )}
        >
            <div className="relative z-10 flex items-center gap-4">
                <div
                    className={cn(
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border transition-transform group-hover:scale-105',
                        themes[theme],
                    )}
                >
                    <Icon size={24} strokeWidth={1.5} />
                </div>
                <div>
                    <p className="text-3xl font-extrabold leading-none tracking-tight text-foreground">{value}</p>
                    <p className="mt-1 text-[11px] font-bold uppercase tracking-widest text-muted-foreground">{title}</p>
                </div>
            </div>
            <Icon size={80} className="pointer-events-none absolute -bottom-4 -right-4 text-muted/40 transition-colors group-hover:text-muted/60" />
            {trend !== null && trend !== undefined ? (
                <div className="relative z-10 mt-2 flex items-center gap-1">
                    <span
                        className={cn(
                            'flex items-center gap-0.5 text-xs font-semibold',
                            trend >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive',
                        )}
                    >
                        {trend >= 0 ? '↑' : '↓'} {Math.abs(trend)}%
                    </span>
                    {trendLabel ? <span className="text-xs text-muted-foreground">{trendLabel}</span> : null}
                </div>
            ) : null}
        </div>
    );
}
