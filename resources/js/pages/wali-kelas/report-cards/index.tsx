import { Head, Link, router } from '@inertiajs/react';
import { Eye, FileDown, ScrollText } from 'lucide-react';
import { useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyahClass, Semester, Student } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Raport Kelas', href: '/wali-kelas/report-cards' },
];

type StudentWithReport = Student & { report_card?: { id: number; wali_kelas_notes: string | null; generated_at: string | null } | null };

type Props = {
    classes: Pick<DiniyahClass, 'id' | 'name' | 'grade_level_id'>[];
    semesters: (Pick<Semester, 'id' | 'name' | 'academic_year_id'> & { academic_year?: { id: number; name: string } })[];
    students: StudentWithReport[];
    filters: { class_id?: string; semester_id?: string };
};

export default function WaliKelasReportIndex({ classes, semesters, students, filters }: Props) {
    const [classId, setClassId] = useState(filters.class_id ?? '');
    const [semesterId, setSemesterId] = useState(filters.semester_id ?? '');

    function loadStudents() {
        if (classId && semesterId) {
            router.get('/wali-kelas/report-cards', { class_id: classId, semester_id: semesterId }, { preserveState: true });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Raport Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Raport Kelas" description="Input catatan wali kelas dan cetak raport santri di kelas Anda" />
                <FlashMessage />

                {classes.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <ScrollText className="mx-auto mb-2 size-8" />
                        Anda belum ditugaskan sebagai wali kelas. Hubungi admin untuk informasi lebih lanjut.
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-1">
                                <Label className="text-xs">Kelas</Label>
                                <Select value={classId} onValueChange={setClassId}>
                                    <SelectTrigger className="w-44"><SelectValue placeholder="Pilih kelas" /></SelectTrigger>
                                    <SelectContent>
                                        {classes.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Semester</Label>
                                <Select value={semesterId} onValueChange={setSemesterId}>
                                    <SelectTrigger className="w-52"><SelectValue placeholder="Pilih semester" /></SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.academic_year?.name} - {s.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button size="sm" onClick={loadStudents}>Tampilkan</Button>
                        </div>

                        {students.length > 0 && (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium w-12">No</th>
                                            <th className="px-4 py-3 text-left font-medium">NIS</th>
                                            <th className="px-4 py-3 text-left font-medium">Nama</th>
                                            <th className="px-4 py-3 text-center font-medium">Status Raport</th>
                                            <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {students.map((student, i) => (
                                            <tr key={student.id} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="px-4 py-2 text-muted-foreground">{i + 1}</td>
                                                <td className="px-4 py-2 font-mono">{student.nis}</td>
                                                <td className="px-4 py-2 font-medium">{student.full_name}</td>
                                                <td className="px-4 py-2 text-center">
                                                    {student.report_card?.generated_at ? (
                                                        <Badge variant="default">Sudah</Badge>
                                                    ) : (
                                                        <Badge variant="outline">Belum</Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={`/wali-kelas/report-cards/preview?student_id=${student.id}&semester_id=${semesterId}&class_id=${classId}`}>
                                                                <Eye className="mr-1 size-3" /> Preview
                                                            </Link>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <a href={`/wali-kelas/report-cards/preview-blade?student_id=${student.id}&semester_id=${semesterId}`} target="_blank" rel="noreferrer">
                                                                <Eye className="mr-1 size-3" /> Preview Blade
                                                            </a>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <a href={`/wali-kelas/report-cards/pdf?student_id=${student.id}&semester_id=${semesterId}`} target="_blank" rel="noreferrer">
                                                                <FileDown className="mr-1 size-3" /> PDF
                                                            </a>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {students.length === 0 && (filters.class_id || filters.semester_id) && (
                            <div className="rounded-lg border p-8 text-center text-muted-foreground">
                                <ScrollText className="mx-auto mb-2 size-8" />
                                Pilih kelas dan semester untuk melihat daftar santri.
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
