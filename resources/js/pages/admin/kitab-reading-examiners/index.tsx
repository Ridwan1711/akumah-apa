import { Head, router, useForm } from '@inertiajs/react';
import { BookOpenCheck, Trash2, UserCheck } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Penguji Baca Kitab', href: '/admin/kitab-reading-examiners' },
];

type SemesterOption = {
    id: number;
    name: string;
    academic_year_name?: string | null;
    is_active?: boolean;
};

type Assignment = {
    id: number;
    examiner?: Pick<User, 'id' | 'name'>;
    school_class?: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>;
    period?: {
        id: number;
        academic_year?: { id: number; name: string };
        semester?: { id: number; name: string };
    };
};

type Props = {
    assignments: Assignment[];
    examiners: Pick<User, 'id' | 'name'>[];
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id' | 'student_gender'>[];
    semesters: SemesterOption[];
    selectedSemesterId: number | null;
};

export default function KitabReadingExaminerIndex({ assignments, examiners, classes, semesters, selectedSemesterId }: Props) {
    const selectedSemester = selectedSemesterId ? String(selectedSemesterId) : '';
    const form = useForm({
        examiner_id: '',
        class_id: '',
        semester_id: selectedSemester,
    });

    function changeSemester(value: string) {
        form.setData('semester_id', value);
        router.get('/admin/kitab-reading-examiners', { semester_id: value }, { preserveState: true, preserveScroll: true });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/kitab-reading-examiners', {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({
                    examiner_id: '',
                    class_id: '',
                    semester_id: form.data.semester_id,
                });
            },
        });
    }

    function destroyAssignment(id: number) {
        router.delete(`/admin/kitab-reading-examiners/${id}`, {
            data: { semester_id: form.data.semester_id },
            preserveScroll: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penguji Baca Kitab" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Penguji Baca Kitab"
                    description="Tetapkan user penguji khusus untuk mengisi nilai kemahiran membaca kitab per kelas dan semester."
                />
                <FlashMessage />

                <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-1">
                            <Label className="text-xs">Semester</Label>
                            <Select value={form.data.semester_id} onValueChange={changeSemester}>
                                <SelectTrigger className="w-60">
                                    <SelectValue placeholder="Pilih semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((semester) => (
                                        <SelectItem key={semester.id} value={String(semester.id)}>
                                            {semester.academic_year_name} - {semester.name}
                                            {semester.is_active ? ' (Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label className="text-xs">Kelas</Label>
                            <Select value={form.data.class_id} onValueChange={(value) => form.setData('class_id', value)}>
                                <SelectTrigger className="w-60">
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
                            <Label className="text-xs">Penguji</Label>
                            <Select value={form.data.examiner_id} onValueChange={(value) => form.setData('examiner_id', value)}>
                                <SelectTrigger className="w-64">
                                    <SelectValue placeholder="Pilih user penguji" />
                                </SelectTrigger>
                                <SelectContent>
                                    {examiners.map((examiner) => (
                                        <SelectItem key={examiner.id} value={String(examiner.id)}>
                                            {examiner.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit" disabled={form.processing || !form.data.semester_id || !form.data.class_id || !form.data.examiner_id}>
                            <UserCheck className="mr-1 size-4" />
                            Tugaskan
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Penguji yang ditugaskan di sini tidak harus guru mapel. Akun santri, wali santri, dan alumni tidak bisa dipilih.
                    </p>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="w-12 px-3 py-3 text-left">No</th>
                                <th className="px-3 py-3 text-left">Penguji</th>
                                <th className="px-3 py-3 text-left">Kelas</th>
                                <th className="px-3 py-3 text-left">Periode</th>
                                <th className="px-3 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assignments.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                        <BookOpenCheck className="mx-auto mb-2 size-8" />
                                        Belum ada penguji baca kitab pada semester ini.
                                    </td>
                                </tr>
                            ) : (
                                assignments.map((assignment, index) => (
                                    <tr key={assignment.id} className="border-b last:border-0">
                                        <td className="px-3 py-2 text-muted-foreground">{index + 1}</td>
                                        <td className="px-3 py-2 font-medium">{assignment.examiner?.name}</td>
                                        <td className="px-3 py-2">{assignment.school_class?.name}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant="outline">
                                                {assignment.period?.academic_year?.name} - {assignment.period?.semester?.name}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button type="button" variant="destructive" size="sm" onClick={() => destroyAssignment(assignment.id)}>
                                                <Trash2 className="mr-1 size-3" />
                                                Hapus
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
