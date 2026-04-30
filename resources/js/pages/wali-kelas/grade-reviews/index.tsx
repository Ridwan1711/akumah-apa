import { Head, router, useForm } from '@inertiajs/react';
import { CheckCheck, ScrollText } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyahClass, Semester } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Review Nilai Kelas', href: '/wali-kelas/grade-reviews' },
];

type Props = {
    classes: Pick<DiniyahClass, 'id' | 'name' | 'grade_level_id'>[];
    semesters: (Pick<Semester, 'id' | 'name' | 'academic_year_id'> & { academic_year?: { id: number; name: string } })[];
    subjects: { id: number; name: string }[];
    students: { id: number; nis: string; full_name: string }[];
    matrix: Record<number, Record<number, { average: number | null; status: string | null }>>;
    reviewStats: {
        total_students: number;
        total_subjects: number;
        submitted_cells: number;
        finalized_cells: number;
    };
    filters: { class_id?: string; semester_id?: string };
};

export default function WaliKelasGradeReviewIndex({
    classes,
    semesters,
    subjects,
    students,
    matrix,
    reviewStats,
    filters,
}: Props) {
    const classId = filters.class_id ?? '';
    const semesterId = filters.semester_id ?? '';
    const reviewForm = useForm({
        class_id: classId,
        semester_id: semesterId,
    });

    function loadRecap(nextClassId: string, nextSemesterId: string) {
        if (nextClassId && nextSemesterId) {
            router.get('/wali-kelas/grade-reviews', { class_id: nextClassId, semester_id: nextSemesterId }, { preserveState: true });
        }
    }

    function submitReview(e: React.FormEvent) {
        e.preventDefault();
        reviewForm.post('/wali-kelas/grade-reviews/review');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review Nilai Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Review Nilai Kelas" description="Rekap nilai lintas mapel wajib untuk kelas yang Anda ampu." />
                <FlashMessage />

                {classes.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <ScrollText className="mx-auto mb-2 size-8" />
                        Anda belum ditugaskan sebagai wali kelas.
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-1">
                                <Label className="text-xs">Kelas</Label>
                                <Select
                                    value={classId}
                                    onValueChange={(value) => {
                                        reviewForm.setData('class_id', value);
                                        loadRecap(value, semesterId);
                                    }}
                                >
                                    <SelectTrigger className="w-44"><SelectValue placeholder="Pilih kelas" /></SelectTrigger>
                                    <SelectContent>
                                        {classes.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Semester</Label>
                                <Select
                                    value={semesterId}
                                    onValueChange={(value) => {
                                        reviewForm.setData('semester_id', value);
                                        loadRecap(classId, value);
                                    }}
                                >
                                    <SelectTrigger className="w-52"><SelectValue placeholder="Pilih semester" /></SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.academic_year?.name} - {s.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                size="sm"
                                onClick={submitReview}
                                disabled={!classId || !semesterId || reviewForm.processing || students.length === 0}
                            >
                                <CheckCheck className="mr-1 size-4" /> Finalisasi Rekap
                            </Button>
                        </div>

                        {(classId || semesterId) && (
                            <div className="flex flex-wrap gap-2 text-sm">
                                <Badge variant="outline">Santri: {reviewStats.total_students}</Badge>
                                <Badge variant="outline">Mapel wajib: {reviewStats.total_subjects}</Badge>
                                <Badge variant="secondary">Submitted: {reviewStats.submitted_cells}</Badge>
                                <Badge>Finalized: {reviewStats.finalized_cells}</Badge>
                            </div>
                        )}

                        {students.length > 0 && subjects.length > 0 && (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50">
                                        <tr>
                                            <th className="w-12 px-3 py-3 text-left">No</th>
                                            <th className="min-w-48 px-3 py-3 text-left">Nama</th>
                                            {subjects.map((subject) => (
                                                <th key={subject.id} className="min-w-36 px-3 py-3 text-center">{subject.name}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {students.map((student, index) => (
                                            <tr key={student.id} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="px-3 py-2 text-muted-foreground">{index + 1}</td>
                                                <td className="px-3 py-2">
                                                    <div className="font-medium">{student.full_name}</div>
                                                    <div className="text-xs text-muted-foreground">{student.nis}</div>
                                                </td>
                                                {subjects.map((subject) => {
                                                    const cell = matrix?.[student.id]?.[subject.id];
                                                    return (
                                                        <td key={subject.id} className="px-3 py-2 text-center">
                                                            <div className="font-semibold">{cell?.average?.toFixed(2) ?? '-'}</div>
                                                            <div className="text-xs text-muted-foreground">{cell?.status ?? '-'}</div>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
