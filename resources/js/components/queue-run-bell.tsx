import { usePage } from '@inertiajs/react';
import { Clock3, Loader2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type QueueRunListItem, useQueueRunsPoll } from '@/hooks/use-queue-runs-poll';
import { cn } from '@/lib/utils';

const statusLabel: Record<QueueRunListItem['status'], string> = {
    queued: 'Menunggu',
    processing: 'Diproses',
    completed: 'Selesai',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
};

export function QueueRunBell() {
    const { auth } = usePage<{ auth: { user?: { id: number } } }>().props;
    const [open, setOpen] = useState(false);

    const { runs, activeCount, queueLoading: loading } = useQueueRunsPoll({
        enabled: Boolean(auth?.user),
        limit: 15,
        panelOpen: open,
    });

    const hasProcessing = useMemo(
        () => runs.some((run) => run.status === 'queued' || run.status === 'processing'),
        [runs]
    );

    if (!auth?.user) {
        return null;
    }

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Clock3 className={cn('size-5', hasProcessing && 'animate-pulse')} />
                    {activeCount > 0 && (
                        <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                            {activeCount > 9 ? '9+' : activeCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-96">
                <div className="border-b px-3 py-2">
                    <span className="font-semibold">Aktivitas Queue</span>
                </div>
                <div className="max-h-80 overflow-y-auto">
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="size-8 animate-spin text-muted-foreground" />
                        </div>
                    ) : runs.length === 0 ? (
                        <div className="py-12 text-center text-sm text-muted-foreground">Belum ada proses queue</div>
                    ) : (
                        <div className="divide-y">
                            {runs.map((run) => (
                                <div key={run.id} className="space-y-2 px-3 py-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{run.title}</p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {run.file_name || `#${run.uuid}`}
                                            </p>
                                        </div>
                                        <span
                                            className={cn(
                                                'shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold',
                                                run.status === 'completed' && 'bg-emerald-100 text-emerald-700',
                                                run.status === 'processing' && 'bg-blue-100 text-blue-700',
                                                run.status === 'queued' && 'bg-amber-100 text-amber-700',
                                                run.status === 'failed' && 'bg-rose-100 text-rose-700',
                                                run.status === 'cancelled' && 'bg-slate-200 text-slate-700'
                                            )}
                                        >
                                            {statusLabel[run.status]}
                                        </span>
                                    </div>
                                    <div className="h-1.5 w-full overflow-hidden rounded bg-muted">
                                        <div
                                            className={cn(
                                                'h-full rounded transition-all',
                                                run.status === 'failed' ? 'bg-rose-500' : 'bg-primary'
                                            )}
                                            style={{ width: `${Math.max(0, Math.min(100, run.progress_percent))}%` }}
                                        />
                                    </div>
                                    <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                        <span>
                                            {run.processed_rows} / {run.total_rows || '?'} baris
                                        </span>
                                        <span>{run.created_at ?? '-'}</span>
                                    </div>
                                    {(run.failed_count > 0 || run.error_message) && (
                                        <p className="line-clamp-2 text-[11px] text-rose-600">
                                            {run.error_message || `${run.failed_count} baris gagal`}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
