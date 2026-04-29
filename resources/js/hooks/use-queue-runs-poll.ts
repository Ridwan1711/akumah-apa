import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

const POLL_ACTIVE_MS = 15_000;
const POLL_IDLE_MS = 60_000;
const DEFAULT_STALE_ON_OPEN_MS = 3000;

export type QueueRunListItem = {
    id: number;
    uuid: string;
    title: string;
    type: string;
    job_type: string | null;
    status: 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled';
    file_name: string | null;
    progress_percent: number;
    processed_rows: number;
    total_rows: number;
    created_count: number;
    updated_count: number;
    skipped_count: number;
    failed_count: number;
    error_message: string | null;
    created_at: string | null;
    requested_by?: string | null;
    can_retry?: boolean;
};

type QueueRunApiResponse = {
    data: QueueRunListItem[];
    meta?: {
        active_count?: number;
        can_view_all?: boolean;
        current_scope?: 'my' | 'all';
    };
};

type Options = {
    enabled: boolean;
    scope?: 'my' | 'all';
    limit?: number;
    /** Refetch when panel/dropdown opens only if last fetch is older than this. */
    openStaleAfterMs?: number;
    panelOpen?: boolean;
    /** When API returns `meta.current_scope` (e.g. non-admin forced to `my`). */
    onServerScope?: (scope: 'my' | 'all') => void;
};

export function useQueueRunsPoll({
    enabled,
    scope = 'my',
    limit = 15,
    openStaleAfterMs = DEFAULT_STALE_ON_OPEN_MS,
    panelOpen = false,
    onServerScope,
}: Options) {
    const [runs, setRuns] = useState<QueueRunListItem[]>([]);
    const [activeCount, setActiveCount] = useState(0);
    const [canViewAll, setCanViewAll] = useState(false);
    const [queueLoading, setQueueLoading] = useState(false);

    const hasActiveRef = useRef(false);
    const lastFetchAtRef = useRef(0);
    const wasPanelOpenRef = useRef(false);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const applyResponse = useCallback(
        (data: QueueRunApiResponse) => {
            setRuns(Array.isArray(data?.data) ? data.data : []);
            setActiveCount(data?.meta?.active_count ?? 0);
            hasActiveRef.current = (data?.meta?.active_count ?? 0) > 0;
            setCanViewAll(Boolean(data?.meta?.can_view_all));
            if (data?.meta?.current_scope) {
                onServerScope?.(data.meta.current_scope);
            }
        },
        [onServerScope]
    );

    const fetchQueueRuns = useCallback(
        async (opts?: { silent?: boolean }): Promise<void> => {
            if (!enabled) {
                return;
            }
            if (!opts?.silent) {
                setQueueLoading(true);
            }
            try {
                const { data } = await axios.get<QueueRunApiResponse>('/queue-runs', {
                    params: { scope, limit },
                });
                applyResponse(data);
                lastFetchAtRef.current = Date.now();
            } catch {
                setRuns([]);
                setActiveCount(0);
                hasActiveRef.current = false;
            } finally {
                if (!opts?.silent) {
                    setQueueLoading(false);
                }
            }
        },
        [applyResponse, enabled, limit, scope]
    );

    const refetch = useCallback(
        async (options?: { silent?: boolean }) => {
            await fetchQueueRuns({ silent: options?.silent });
        },
        [fetchQueueRuns]
    );

    const refetchIfStale = useCallback(async () => {
        if (!enabled) {
            return;
        }
        if (Date.now() - lastFetchAtRef.current >= openStaleAfterMs) {
            await fetchQueueRuns({ silent: false });
        }
    }, [enabled, fetchQueueRuns, openStaleAfterMs]);

    useEffect(() => {
        if (panelOpen && !wasPanelOpenRef.current) {
            void refetchIfStale();
        }
        wasPanelOpenRef.current = panelOpen;
    }, [panelOpen, refetchIfStale]);

    useEffect(() => {
        if (!enabled) {
            setRuns([]);
            setActiveCount(0);
            setQueueLoading(false);
            if (timeoutRef.current !== null) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }
            return;
        }

        let cancelled = false;

        const clearPollTimer = () => {
            if (timeoutRef.current !== null) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }
        };

        const scheduleAfterFetch = () => {
            if (cancelled) {
                return;
            }
            if (document.visibilityState === 'hidden') {
                return;
            }
            const delay = hasActiveRef.current ? POLL_ACTIVE_MS : POLL_IDLE_MS;
            clearPollTimer();
            timeoutRef.current = setTimeout(pollOnce, delay);
        };

        const pollOnce = async () => {
            if (cancelled || document.visibilityState === 'hidden') {
                return;
            }
            await fetchQueueRuns({ silent: true });
            if (cancelled) {
                return;
            }
            scheduleAfterFetch();
        };

        const bootstrap = async () => {
            await fetchQueueRuns({ silent: false });
            if (cancelled) {
                return;
            }
            scheduleAfterFetch();
        };

        void bootstrap();

        const onVisibility = () => {
            if (document.visibilityState === 'hidden') {
                clearPollTimer();
                return;
            }
            void (async () => {
                if (cancelled) {
                    return;
                }
                await fetchQueueRuns({ silent: true });
                if (cancelled) {
                    return;
                }
                scheduleAfterFetch();
            })();
        };
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            cancelled = true;
            clearPollTimer();
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled, fetchQueueRuns, scope, limit]);

    return {
        runs,
        activeCount,
        canViewAll,
        queueLoading,
        refetch,
        refetchIfStale,
    };
}
