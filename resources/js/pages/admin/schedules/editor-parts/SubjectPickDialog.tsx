import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ScheduleMatrixPengampu } from '@/types';

type Props = {
    open: boolean;
    className: string;
    options: ScheduleMatrixPengampu[];
    progressByPengampuId: Record<number, { allocated: number; target: number; isFull: boolean }>;
    onPick: (pengampu: ScheduleMatrixPengampu) => void;
    onCancel: () => void;
};

export default function SubjectPickDialog({
    open,
    className,
    options,
    progressByPengampuId,
    onPick,
    onCancel,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={(o) => (!o ? onCancel() : undefined)}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Pilih mata pelajaran</DialogTitle>
                    <DialogDescription>
                        Di kelas <strong>{className}</strong>, guru ini mengampu lebih dari satu mapel.
                        Pilih mapel yang akan dijadwalkan di slot ini.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2 py-2">
                    {options.map((p) => {
                        const progress = progressByPengampuId[p.id] ?? {
                            allocated: 0,
                            target: p.target_jam ?? 1,
                            isFull: false,
                        };
                        return (
                            <Button
                                key={p.id}
                                type="button"
                                variant="outline"
                                className="h-auto justify-start py-3 text-left"
                                onClick={() => onPick(p)}
                                disabled={progress.isFull}
                            >
                                <div className="flex w-full items-center justify-between gap-3">
                                    <span className="font-medium">{p.subject?.name ?? `Mapel #${p.subject_id}`}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {progress.allocated}/{progress.target} jam
                                    </span>
                                </div>
                            </Button>
                        );
                    })}
               
                </div>
            </DialogContent>
        </Dialog>
    );
}
