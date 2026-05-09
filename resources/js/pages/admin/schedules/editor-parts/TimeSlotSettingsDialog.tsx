import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ScheduleTimeSlot } from '@/types';

/** Sen–Kam & Sab–Ahad; Jumat tidak dipakai kolom matrix (selaras `AcademicSchedule::TEACHING_DAYS`). */
const MAX_SCHEDULE_MATRIX_DAYS = 6;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    scheduleSetId: number;
    initialJamCount: number;
    initialDayCount: number;
    initialSlots: ScheduleTimeSlot[];
};

export default function TimeSlotSettingsDialog({
    open,
    onOpenChange,
    scheduleSetId,
    initialJamCount,
    initialDayCount,
    initialSlots,
}: Props) {
    const [jamCount, setJamCount] = useState(initialJamCount);
    const [dayCount, setDayCount] = useState(initialDayCount);
    const [slots, setSlots] = useState<ScheduleTimeSlot[]>(initialSlots);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (open) {
            setJamCount(initialJamCount);
            setDayCount(initialDayCount);
            setSlots(ensureSlots(initialJamCount, initialSlots));
        }
    }, [open, initialJamCount, initialDayCount, initialSlots]);

    useEffect(() => {
        setSlots((prev) => ensureSlots(jamCount, prev));
    }, [jamCount]);

    function updateSlot(jamNo: number, key: 'time_start' | 'time_end', value: string) {
        setSlots((prev) =>
            prev.map((s) => (s.jam_no === jamNo ? { ...s, [key]: value } : s)),
        );
    }

    function submit() {
        setBusy(true);
        router.put(
            `/admin/schedule-sets/${scheduleSetId}/time-slots`,
            {
                jam_count: jamCount,
                day_count: dayCount,
                slots: slots.map((s) => ({
                    jam_no: s.jam_no,
                    time_start: s.time_start,
                    time_end: s.time_end,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Pengaturan jam</DialogTitle>
                </DialogHeader>
                <div className="grid gap-3 py-2">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label>Jumlah Hari KBM</Label>
                            <Input
                                type="number"
                                min={1}
                                max={MAX_SCHEDULE_MATRIX_DAYS}
                                value={dayCount}
                                onChange={(e) =>
                                    setDayCount(
                                        Math.max(
                                            1,
                                            Math.min(MAX_SCHEDULE_MATRIX_DAYS, Number(e.target.value) || 1),
                                        ),
                                    )
                                }
                            />
                            <p className="text-[11px] text-muted-foreground">
                                Jumat libur. Kolom mengikuti Senin–Kamis lalu Sabtu–Ahad (bukan Minggu sebagai libur).
                            </p>
                        </div>
                        <div className="grid gap-1">
                            <Label>Jumlah Jam</Label>
                            <Input
                                type="number"
                                min={1}
                                max={20}
                                value={jamCount}
                                onChange={(e) => setJamCount(Math.max(1, Math.min(20, Number(e.target.value) || 1)))}
                            />
                        </div>
                    </div>
                    <div className="mt-2 grid gap-2">
                        <Label className="text-sm">Slot waktu</Label>
                        <div className="max-h-[320px] space-y-2 overflow-auto rounded-md border p-2">
                            {slots.map((slot) => (
                                <div key={slot.jam_no} className="grid grid-cols-[60px_1fr_1fr] items-center gap-2">
                                    <div className="text-xs font-medium text-muted-foreground">Jam {slot.jam_no}</div>
                                    <Input
                                        type="time"
                                        value={slot.time_start}
                                        onChange={(e) => updateSlot(slot.jam_no, 'time_start', e.target.value)}
                                    />
                                    <Input
                                        type="time"
                                        value={slot.time_end}
                                        onChange={(e) => updateSlot(slot.jam_no, 'time_end', e.target.value)}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Batal
                    </Button>
                    <Button type="button" onClick={submit} disabled={busy}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Simpan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ensureSlots(jamCount: number, current: ScheduleTimeSlot[]): ScheduleTimeSlot[] {
    const byJam = new Map<number, ScheduleTimeSlot>();
    current.forEach((s) => byJam.set(s.jam_no, s));

    const next: ScheduleTimeSlot[] = [];
    for (let i = 1; i <= jamCount; i++) {
        const existing = byJam.get(i);
        if (existing) {
            next.push(existing);
        } else {
            const prev = byJam.get(i - 1) ?? next[next.length - 1];
            const defaultStart = prev ? addMinutes(prev.time_end, 15) : '07:00';
            const defaultEnd = addMinutes(defaultStart, 45);
            next.push({ jam_no: i, time_start: defaultStart, time_end: defaultEnd });
        }
    }
    return next;
}

function addMinutes(hhmm: string, minutes: number): string {
    const [h, m] = hhmm.split(':').map(Number);
    const total = (h ?? 7) * 60 + (m ?? 0) + minutes;
    const hh = Math.floor(((total % (24 * 60)) + 24 * 60) % (24 * 60) / 60);
    const mm = ((total % 60) + 60) % 60;
    return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}
