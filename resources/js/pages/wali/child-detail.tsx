import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyyahScore, Semester, Student, StudentViolation, TahfidzProgress } from '@/types';

type Props = {
    student: Student;
    semesters: (Semester & { academic_year?: { id: number; name: string } })[];
    currentSemesterId: number | null;
    semester: (Semester & { academic_year?: { id: number; name: string } }) | null;
    grades: DiniyyahScore[];
    recentTahfidz: TahfidzProgress[];
    recentViolations: StudentViolation[];
};

export default function WaliChildDetail({ student, semesters, currentSemesterId, grades, recentTahfidz, recentViolations }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Anak', href: '/wali/children' },
        { title: student.full_name, href: '#' },
    ];

    const [semesterId, setSemesterId] = useState(String(currentSemesterId ?? ''));

    function changeSemester(v: string) {
        setSemesterId(v);
        router.get(`/wali/children/${student.id}`, { semester_id: v }, { preserveState: true });
    }

    const avg = grades.length > 0
        ? (grades.reduce((acc, g) => acc + Number(g.score ?? 0), 0) / grades.length).toFixed(1)
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title={student.full_name} description={`NIS: ${student.nis} | Kelas: ${student.current_class?.name ?? '-'}`} />
                <div>
                    <Link href={`/wali/children/${student.id}/schedule`}>
                        <Button type="button" variant="outline">Lihat Jadwal Anak</Button>
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border p-4 text-center">
                        <p className="text-3xl font-bold text-green-600">{student.tahfidz_summary?.total_juz_completed ?? 0}</p>
                        <p className="text-sm text-muted-foreground">Juz Hafalan</p>
                    </div>
                    <div className="rounded-xl border p-4 text-center">
                        <p className="text-3xl font-bold text-red-600">{student.violation_summary?.total_points ?? 0}</p>
                        <p className="text-sm text-muted-foreground">Poin Pelanggaran</p>
                    </div>
                    <div className="rounded-xl border p-4 text-center">
                        <p className="text-3xl font-bold">{avg ?? '-'}</p>
                        <p className="text-sm text-muted-foreground">Rata-rata Nilai</p>
                    </div>
                </div>

                <div className="flex items-end gap-3">
                    <div className="grid gap-1">
                        <Label className="text-xs">Semester</Label>
                        <Select value={semesterId} onValueChange={changeSemester}>
                            <SelectTrigger className="w-52"><SelectValue placeholder="Pilih semester" /></SelectTrigger>
                            <SelectContent>
                                {semesters.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.academic_year?.name} - {s.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {grades.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Nilai Kitab</h3>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Mata Pelajaran</th>
                                    <th className="px-3 py-2 text-center font-medium w-20">Nilai</th>
                                    <th className="px-3 py-2 text-center font-medium w-16">Huruf</th>
                                </tr>
                            </thead>
                            <tbody>
                                {grades.map((g) => (
                                    <tr key={g.id} className="border-b last:border-0">
                                        <td className="px-3 py-2">{g.subject?.name}</td>
                                        <td className="px-3 py-2 text-center font-bold">{g.score}</td>
                                        <td className="px-3 py-2 text-center"><Badge variant="outline">{g.grade_letter}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {recentTahfidz.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Tahfidz Terbaru</h3>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Juz</th>
                                    <th className="px-3 py-2 text-left font-medium">Surah</th>
                                    <th className="px-3 py-2 text-left font-medium">Tipe</th>
                                    <th className="px-3 py-2 text-left font-medium">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentTahfidz.map((t) => (
                                    <tr key={t.id} className="border-b last:border-0">
                                        <td className="px-3 py-2">{t.juz}</td>
                                        <td className="px-3 py-2">{t.surah_to && t.surah_to !== t.surah_from ? `${t.surah_from} - ${t.surah_to}` : t.surah_from}</td>
                                        <td className="px-3 py-2"><Badge variant={t.type === 'ziyadah' ? 'default' : 'secondary'}>{t.type}</Badge></td>
                                        <td className="px-3 py-2">{t.grade}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {recentViolations.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Pelanggaran Terbaru</h3>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Tanggal</th>
                                    <th className="px-3 py-2 text-left font-medium">Jenis</th>
                                    <th className="px-3 py-2 text-left font-medium">Kategori</th>
                                    <th className="px-3 py-2 text-center font-medium">Poin</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentViolations.map((v) => (
                                    <tr key={v.id} className="border-b last:border-0">
                                        <td className="px-3 py-2">{v.date}</td>
                                        <td className="px-3 py-2">{v.violation_type?.name}</td>
                                        <td className="px-3 py-2"><Badge variant={v.violation_type?.category === 'berat' ? 'destructive' : 'outline'}>{v.violation_type?.category}</Badge></td>
                                        <td className="px-3 py-2 text-center">{v.violation_type?.points}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
