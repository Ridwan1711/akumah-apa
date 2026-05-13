import { Head, router, useForm } from '@inertiajs/react';
import { BookOpenCheck, Save } from 'lucide-react';
import { useEffect } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, Semester } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nilai Baca Kitab', href: '/admin/kitab-reading-assessments' },
];

type StudentRow = {
    id: number;
    nis: string;
    full_name: string;
    gender: 'L' | 'P';
};

type AssessmentRow = {
    id: number;
    student_id: number;
    score: number | string;
    notes: string | null;
    examiner?: { id: number; name: string } | null;
    assessed_at: string | null;
};

type FormAssessment = {
    student_id: number;
    score: string;
    notes: string;
};

type Props = {
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id' | 'student_gender'>[];
    semesters: (Pick<Semester, 'id' | 'name' | 'academic_year_id' | 'start_date' | 'end_date'> & {
        academic_year?: { id: number; name: string };
    })[];
    students: StudentRow[];
    assessments: Record<number, AssessmentRow>;
    filters: { class_id?: string; semester_id?: string };
};

export default function KitabReadingAssessmentIndex({ classes, semesters, students, assessments, filters }: Props) {
    const classId = filters.class_id ?? '';
    const semesterId = filters.semester_id ?? '';
    const form = useForm<{ class_id: string; semester_id: string; assessments: FormAssessment[] }>({
        class_id: classId,
        semester_id: semesterId,
        assessments: [],
    });

    useEffect(() => {
        form.setData({
            class_id: classId,
            semester_id: semesterId,
            assessments: students.map((student) => {
                const existing = assessments?.[student.id];

                return {
                    student_id: student.id,
                    score: existing?.score !== undefined ? String(existing.score) : '',
                    notes: existing?.notes ?? '',
                };
            }),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [classId, semesterId, students, assessments]);

    function loadRows(nextClassId: string, nextSemesterId: string) {
        if (nextClassId && nextSemesterId) {
            router.get(
                '/admin/kitab-reading-assessments',
                { class_id: nextClassId, semester_id: nextSemesterId },
                { preserveState: true, preserveScroll: true },
            );
        }
    }

    function updateAssessment(studentId: number, patch: Partial<FormAssessment>) {
        form.setData(
            'assessments',
            form.data.assessments.map((row) => (row.student_id === studentId ? { ...row, ...patch } : row)),
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/kitab-reading-assessments', {
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nilai Baca Kitab" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Nilai Kemahiran Membaca Kitab"
                    description="Penguji mengisi nilai membaca kitab per santri sebagai komponen kenaikan kelas."
                />
                <FlashMessage />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
                    <div className="grid gap-1">
                        <Label className="text-xs">Kelas</Label>
                        <Select
                            value={form.data.class_id}
                            onValueChange={(value) => {
                                form.setData('class_id', value);
                                loadRows(value, form.data.semester_id);
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
                            value={form.data.semester_id}
                            onValueChange={(value) => {
                                form.setData('semester_id', value);
                                loadRows(form.data.class_id, value);
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
                </div>

                {!classId || !semesterId ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <BookOpenCheck className="mx-auto mb-2 size-8" />
                        Pilih kelas dan semester untuk mulai mengisi nilai baca kitab.
                    </div>
                ) : (
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="w-12 px-3 py-3 text-left">No</th>
                                        <th className="min-w-56 px-3 py-3 text-left">Santri</th>
                                        <th className="w-40 px-3 py-3 text-left">Nilai</th>
                                        <th className="min-w-72 px-3 py-3 text-left">Catatan</th>
                                        <th className="min-w-48 px-3 py-3 text-left">Terakhir Dinilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {students.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                                Tidak ada santri aktif pada kelas ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        students.map((student, index) => {
                                            const row = form.data.assessments.find((item) => item.student_id === student.id);
                                            const existing = assessments?.[student.id];

                                            return (
                                                <tr key={student.id} className="border-b last:border-0">
                                                    <td className="px-3 py-2 text-muted-foreground">{index + 1}</td>
                                                    <td className="px-3 py-2">
                                                        <div className="font-medium">{student.full_name}</div>
                                                        <div className="text-xs text-muted-foreground">{student.nis}</div>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="0.01"
                                                            className="w-28 rounded-md border bg-background px-3 py-2"
                                                            value={row?.score ?? ''}
                                                            onChange={(event) => updateAssessment(student.id, { score: event.target.value })}
                                                            required
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="text"
                                                            className="w-full rounded-md border bg-background px-3 py-2"
                                                            value={row?.notes ?? ''}
                                                            onChange={(event) => updateAssessment(student.id, { notes: event.target.value })}
                                                            placeholder="Opsional"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {existing?.assessed_at ? (
                                                            <div>
                                                                <div>{new Date(existing.assessed_at).toLocaleString('id-ID')}</div>
                                                                <div className="text-xs">{existing.examiner?.name}</div>
                                                            </div>
                                                        ) : (
                                                            '-'
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing || students.length === 0}>
                                <Save className="mr-1 size-4" />
                                {form.processing ? 'Menyimpan...' : 'Simpan Nilai'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
