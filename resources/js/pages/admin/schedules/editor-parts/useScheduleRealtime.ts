import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getEcho } from '@/echo';

export type PresenceMember = {
    id: number;
    name: string;
    role?: string;
    color_seed?: number;
};

export type LockInfo = {
    schedule_set_id: number;
    class_id: number;
    day: number;
    jam_no: number;
    user_id: number;
    user_name: string;
    token?: string;
    expires_at?: string;
};

export type ScheduleRealtimeEventName =
    | 'cell.assigned'
    | 'cell.deleted'
    | 'cell.moved'
    | 'cells.bulk_deleted'
    | 'cells.restored'
    | 'time_slots.updated'
    | 'cell.locked'
    | 'cell.unlocked';

export type ScheduleRealtimeEvent = {
    type: ScheduleRealtimeEventName;
    payload: Record<string, unknown>;
};

type EventHandler = (event: ScheduleRealtimeEvent) => void;

function lockKey(classId: number, day: number, jamNo: number): string {
    return `${classId}:${day}:${jamNo}`;
}

export function useScheduleRealtime(scheduleSetId: number | null | undefined) {
    const [presence, setPresence] = useState<PresenceMember[]>([]);
    const [lockState, setLockState] = useState<Record<string, LockInfo>>({});
    const handlersRef = useRef(new Set<EventHandler>());

    const emit = useCallback((type: ScheduleRealtimeEventName, payload: Record<string, unknown>) => {
        const event: ScheduleRealtimeEvent = { type, payload };
        handlersRef.current.forEach((handler) => handler(event));
    }, []);

    const onEvent = useCallback((handler: EventHandler) => {
        handlersRef.current.add(handler);

        return () => {
            handlersRef.current.delete(handler);
        };
    }, []);

    useEffect(() => {
        if (!scheduleSetId) return;

        const echo = getEcho();
        const channelName = `schedule.set.${scheduleSetId}`;
        const channel = echo.join(channelName);

        channel
            .here((members: PresenceMember[]) => setPresence(members))
            .joining((member: PresenceMember) => {
                setPresence((prev) => {
                    if (prev.some((p) => p.id === member.id)) return prev;
                    return [...prev, member];
                });
            })
            .leaving((member: PresenceMember) => {
                setPresence((prev) => prev.filter((p) => p.id !== member.id));
            })
            .listen('.cell.assigned', (payload: Record<string, unknown>) => emit('cell.assigned', payload))
            .listen('.cell.deleted', (payload: Record<string, unknown>) => emit('cell.deleted', payload))
            .listen('.cell.moved', (payload: Record<string, unknown>) => emit('cell.moved', payload))
            .listen('.cells.bulk_deleted', (payload: Record<string, unknown>) =>
                emit('cells.bulk_deleted', payload),
            )
            .listen('.cells.restored', (payload: Record<string, unknown>) => emit('cells.restored', payload))
            .listen('.time_slots.updated', (payload: Record<string, unknown>) =>
                emit('time_slots.updated', payload),
            )
            .listen('.cell.locked', (payload: Record<string, unknown>) => {
                emit('cell.locked', payload);
                const lock = payload.lock as LockInfo | undefined;
                if (!lock) return;
                setLockState((prev) => ({
                    ...prev,
                    [lockKey(lock.class_id, lock.day, lock.jam_no)]: lock,
                }));
            })
            .listen('.cell.unlocked', (payload: Record<string, unknown>) => {
                emit('cell.unlocked', payload);
                const slot = payload.slot as
                    | { class_id: number; day: number; jam_no: number }
                    | undefined;
                if (!slot) return;
                const key = lockKey(slot.class_id, slot.day, slot.jam_no);
                setLockState((prev) => {
                    if (!prev[key]) return prev;
                    const next = { ...prev };
                    delete next[key];

                    return next;
                });
            });

        channel.error((error: unknown) => {
            console.error('[schedule-realtime] presence subscription error', error);
        });

        return () => {
            echo.leave(channelName);
            setPresence([]);
        };
    }, [emit, scheduleSetId]);

    const lockMap = useMemo(() => new Map(Object.entries(lockState)), [lockState]);

    return {
        presence,
        lockMap,
        onEvent,
    };
}

