import { Head, router, useForm } from '@inertiajs/react';
import { ArrowUpDown, Send } from 'lucide-react';
import { useEffect } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, Semester } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Rekap Kenaikan Kelas', href: '/wali-kelas/class-promotion-recaps' },
];

type PromotionDecision = 'promote' | 'stay' | 'graduate';

type RecapRow = {
    student_id: number;
    student: { id: number; nis: string; full_name: string; gender: 'L' | 'P' };
    academic_average: number | null;
    academic_passed: boolean;
    personality_score: number;
    kitab_reading_score: number | null;
    weighted_total: number;
    system_recommendation: 'promote' | 'stay';
    final_decision: PromotionDecision;
    target_class_id: number | null;
    notes: string | null;
    is_complete: boolean;
    placement_status?: string;
    placement_message?: string | null;
};

type Recap = {
    id: number;
    status: 'draft' | 'submitted' | 'approved' | 'rejected';
    rejection_notes?: string | null;
};

type DecisionRow = {
    student_id: number;
    final_decision: PromotionDecision;
    target_class_id: number | null;
    notes: string;
};

type Props = {
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    semesters: (Pick<Semester, 'id' | 'name' | 'academic_year_id'> & { academic_year?: { id: number; name: string } })[];
    rows: RecapRow[];
    recap: Recap | null;
    filters: { class_id?: string; semester_id?: string };
};

function decisionLabel(value: PromotionDecision) {
    if (value === 'promote') return 'Naik';
    if (value === 'graduate') return 'Lulus';

    return 'Tidak Naik';
}

export default function WaliKelasClassPromotionRecapIndex({ classes, semesters, rows, recap, filters }: Props) {
    const classId = filters.class_id ?? '';
    const semesterId = filters.semester_id ?? '';
    const form = useForm<{ class_id: string; semester_id: string; decisions: DecisionRow[] }>({
        class_id: classId,
        semester_id: semesterId,
        decisions: [],
    });
    const isLocked = recap?.status === 'submitted' || recap?.status === 'approved';
    const isComplete = rows.length > 0 && rows.every((row) => row.is_complete);

    useEffect(() => {
        form.setData({
            class_id: classId,
            semester_id: semesterId,
            decisions: rows.map((row) => ({
                student_id: row.student_id,
                final_decision: row.final_decision,
                target_class_id: row.target_class_id,
                notes: row.notes ?? '',
            })),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [classId, semesterId, rows]);

    function loadRecap(nextClassId: string, nextSemesterId: string) {
        if (nextClassId && nextSemesterId) {
            router.get(
                '/wali-kelas/class-promotion-recaps',
                { class_id: nextClassId, semester_id: nextSemesterId },
                { preserveState: true, preserveScroll: true },
            );
        }
    }

    function updateDecision(studentId: number, patch: Partial<DecisionRow>) {
        form.setData(
            'decisions',
            form.data.decisions.map((row) => (row.student_id === studentId ? { ...row, ...patch } : row)),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/wali-kelas/class-promotion-recaps', { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rekap Kenaikan Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Rekap Kenaikan Kelas"
                    description="Tinjau rekomendasi sistem dari nilai mapel, kepribadian, dan kemahiran baca kitab sebelum diajukan ke Admin Akademik."
                />
                <FlashMessage />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
                    <div className="grid gap-1">
                        <Label className="text-xs">Kelas</Label>
                        <Select
                            value={classId}
                            onValueChange={(value) => {
                                form.setData('class_id', value);
                                loadRecap(value, semesterId);
                            }}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Pilih kelas" />
                            </SelectTrigger>
                            <SelectContent>
                                {classes.map((schoolClass) => (
                                    <SelectItem key={schoolClass.id} value={String(schoolClass.id)}>
                                        {schoolClass.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Semester</Label>
                        <Select
                            value={semesterId}
                            onValueChange={(value) => {
                                form.setData('semester_id', value);
                                loadRecap(classId, value);
                            }}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Pilih semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {semesters.map((semester) => (
                                    <SelectItem key={semester.id} value={String(semester.id)}>
                                        {semester.academic_year?.name} - {semester.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {recap && (
                        <Badge variant={recap.status === 'approved' ? 'default' : recap.status === 'rejected' ? 'destructive' : 'secondary'}>
                            Status: {recap.status}
                        </Badge>
                    )}
                </div>

                {!classId || !semesterId ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <ArrowUpDown className="mx-auto mb-2 size-8" />
                        Pilih kelas dan semester untuk melihat rekap kenaikan kelas.
                    </div>
                ) : (
                    <form onSubmit={submit} className="grid gap-4">
                        {recap?.status === 'rejected' && recap.rejection_notes && (
                            <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                                Catatan penolakan: {recap.rejection_notes}
                            </div>
                        )}
                        {!isComplete && rows.length > 0 && (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                Semua santri harus memiliki nilai mapel dan nilai baca kitab sebelum rekap bisa diajukan.
                            </div>
                        )}

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="w-12 px-3 py-3 text-left">No</th>
                                        <th className="min-w-56 px-3 py-3 text-left">Santri</th>
                                        <th className="px-3 py-3 text-center">Rata-rata Mapel</th>
                                        <th className="px-3 py-3 text-center">Kepribadian</th>
                                        <th className="px-3 py-3 text-center">Baca Kitab</th>
                                        <th className="px-3 py-3 text-center">Total Bobot</th>
                                        <th className="px-3 py-3 text-center">Rekomendasi</th>
                                        <th className="min-w-36 px-3 py-3 text-left">Keputusan Wali</th>
                                        <th className="min-w-56 px-3 py-3 text-left">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="px-3 py-8 text-center text-muted-foreground">
                                                Tidak ada santri aktif atau rekap belum tersedia.
                                            </td>
                                        </tr>
                                    ) : (
                                        rows.map((row, index) => {
                                            const decision = form.data.decisions.find((item) => item.student_id === row.student_id);

                                            return (
                                                <tr key={row.student_id} className="border-b last:border-0">
                                                    <td className="px-3 py-2 text-muted-foreground">{index + 1}</td>
                                                    <td className="px-3 py-2">
                                                        <div className="font-medium">{row.student.full_name}</div>
                                                        <div className="text-xs text-muted-foreground">{row.student.nis}</div>
                                                        {row.placement_status === 'blocked' && (
                                                            <div className="mt-1 text-xs text-destructive">{row.placement_message}</div>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        {row.academic_average?.toFixed(2) ?? '-'}
                                                        {!row.academic_passed && <div className="text-xs text-destructive">Belum &gt; 60</div>}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">{row.personality_score.toFixed(2)}</td>
                                                    <td className="px-3 py-2 text-center">{row.kitab_reading_score?.toFixed(2) ?? '-'}</td>
                                                    <td className="px-3 py-2 text-center font-semibold">{row.weighted_total.toFixed(2)}</td>
                                                    <td className="px-3 py-2 text-center">
                                                        <Badge variant={row.system_recommendation === 'promote' ? 'default' : 'secondary'}>
                                                            {decisionLabel(row.system_recommendation)}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <select
                                                            className="w-full rounded-md border bg-background px-3 py-2"
                                                            value={decision?.final_decision ?? row.final_decision}
                                                            disabled={isLocked}
                                                            onChange={(event) =>
                                                                updateDecision(row.student_id, {
                                                                    final_decision: event.target.value as PromotionDecision,
                                                                })
                                                            }
                                                        >
                                                            <option value="promote">Naik</option>
                                                            <option value="stay">Tidak Naik</option>
                                                            <option value="graduate">Lulus</option>
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            className="w-full rounded-md border bg-background px-3 py-2"
                                                            value={decision?.notes ?? ''}
                                                            disabled={isLocked}
                                                            onChange={(event) => updateDecision(row.student_id, { notes: event.target.value })}
                                                            placeholder="Opsional"
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing || isLocked || !isComplete}>
                                <Send className="mr-1 size-4" />
                                {form.processing ? 'Mengajukan...' : 'Ajukan ke Admin Akademik'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
