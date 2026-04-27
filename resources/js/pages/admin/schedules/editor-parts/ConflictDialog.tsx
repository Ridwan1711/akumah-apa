import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ScheduleConflictResponse } from '@/types';

export type ConflictAction = 'assign' | 'merge' | 'replace_across_classes' | 'replace_cell';

type Props = {
    conflict: ScheduleConflictResponse | null;
    open: boolean;
    onCancel: () => void;
    onConfirm: (action: ConflictAction) => void;
    busy?: boolean;
    context?: {
        teacherName: string;
        subjectName: string;
        dayLabel: string;
        jamNo: number;
        targetClassName: string;
    };
};

export default function ConflictDialog({ conflict, open, onCancel, onConfirm, busy, context }: Props) {
    const title = titleFor(conflict?.type);
    const description = descriptionFor(conflict, context);
    const confirmAction = confirmActionFor(conflict?.type);
    const confirmLabel = confirmLabelFor(conflict?.type);

    return (
        <Dialog open={open} onOpenChange={(o) => (!o ? onCancel() : undefined)}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className="text-sm">{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onCancel} disabled={busy}>
                        Batalkan
                    </Button>
                    {confirmAction && (
                        <Button
                            type="button"
                            onClick={() => onConfirm(confirmAction)}
                            disabled={busy}
                            variant={conflict?.type === 'different_subject_other_class' ? 'destructive' : 'default'}
                        >
                            {busy ? 'Memproses...' : confirmLabel}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function titleFor(type: ScheduleConflictResponse['type'] | undefined): string {
    switch (type) {
        case 'occupied':
            return 'Cell Sudah Terisi';
        case 'same_subject_other_class':
            return 'Pengajaran Digabungkan?';
        case 'different_subject_other_class':
            return 'Bentrok Guru (Mapel Berbeda)';
        default:
            return 'Konfirmasi';
    }
}

function confirmLabelFor(type: ScheduleConflictResponse['type'] | undefined): string {
    switch (type) {
        case 'occupied':
            return 'Ya, Ganti Cell Ini';
        case 'same_subject_other_class':
            return 'Ya, Gabungkan Pengajaran';
        case 'different_subject_other_class':
            return 'Ya, Ganti Jadwal Lama Guru';
        default:
            return 'Ya, Lanjutkan';
    }
}

function descriptionFor(
    conflict: ScheduleConflictResponse | null,
    context?: Props['context'],
): string {
    if (!conflict) return '';
    const activeSubject = context?.subjectName ?? 'mapel terpilih';
    const activeTeacher = context?.teacherName ?? 'guru terpilih';
    const activeDay = context?.dayLabel ?? 'hari terpilih';
    const activeJam = context?.jamNo ?? '-';
    const activeClass = context?.targetClassName ?? 'kelas target';

    switch (conflict.type) {
        case 'occupied':
            return 'Cell yang dipilih sudah terisi oleh pengampu lain. Apakah Anda ingin menggantinya dengan pengampu yang dipilih saat ini?';
        case 'same_subject_other_class': {
            const classes = (conflict.conflicts ?? [])
                .map((c) => c.class_name)
                .filter(Boolean)
                .join(', ');
            return `${activeTeacher} dengan pelajaran ${activeSubject} di ${activeDay} jam ke-${activeJam} sudah mengajar di kelas ${classes || '-'}. Apakah pengajaran digabungkan dengan ${activeClass}?`;
        }
        case 'different_subject_other_class': {
            const rows = (conflict.conflicts ?? [])
                .map((c) => `${c.class_name} (${c.subject_name})`)
                .join(', ');
            return `Terdeteksi ${activeTeacher} sudah terjadwal di ${activeDay} jam ke-${activeJam} untuk pelajaran berbeda: ${rows}. Jika dilanjutkan, jadwal lama di slot ini akan dihapus dan diganti menjadi ${activeSubject} untuk ${activeClass}.`;
        }
        default:
            return '';
    }
}

function confirmActionFor(type: ScheduleConflictResponse['type'] | undefined): ConflictAction | null {
    switch (type) {
        case 'occupied':
            return 'replace_cell';
        case 'same_subject_other_class':
            return 'merge';
        case 'different_subject_other_class':
            return 'replace_across_classes';
        default:
            return null;
    }
}
