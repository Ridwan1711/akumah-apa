import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, CalendarDays, Home, IdCard, Pencil, Star, User, Users } from 'lucide-react';
import { useState } from 'react';
import {
    EmProfileView,
    ProfileHero,
    ProfileInfoRow,
    ProfileSectionCard,
} from '@/components/student-profile';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyyahScore, Semester, Student, StudentViolation } from '@/types';

type Props = {
    student: Student;
    semesters: (Semester & { academic_year?: { id: number; name: string } })[];
    currentSemesterId: number | null;
    semester: (Semester & { academic_year?: { id: number; name: string } }) | null;
    grades: DiniyyahScore[];
    recentViolations: StudentViolation[];
};

export default function WaliChildDetail({ student, semesters, currentSemesterId, grades, recentViolations }: Props) {
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

    const avg =
        grades.length > 0
            ? (grades.reduce((acc, g) => acc + Number(g.score ?? 0), 0) / grades.length).toFixed(1)
            : null;

    const totalViolationPoints = student.violation_summary?.total_points ?? 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />

            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">

                    <div className="mb-6">
                        <h1 className="text-3xl font-bold text-foreground tracking-tight">
                            Detail Anak Saya
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Informasi akademik dan profil {student.full_name}.
                        </p>
                    </div>

                    {/* Hero */}
                    <div className="mb-8">
                        <ProfileHero
                            student={student}
                            extraStats={[
                                { label: 'Poin Pelanggaran', value: totalViolationPoints },
                                ...(avg ? [{ label: 'Rata-rata Nilai', value: avg }] : []),
                            ]}
                            actions={
                                <Link href={`/wali/children/${student.id}/schedule`}>
                                    <Button variant="outline" size="sm" className="border-white/30 bg-white/10 text-white hover:bg-white/20">
                                        Lihat Jadwal
                                    </Button>
                                </Link>
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-6">
                        {/* Informasi Pribadi */}
                        <ProfileSectionCard
                            icon={User}
                            title="Informasi Pribadi"
                            subtitle="Data dasar anak Anda di sistem akademik"
                            action={
                                <Link
                                    href={`/wali/children/${student.id}/edit`}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-white px-3 py-1.5 text-xs font-semibold text-foreground shadow-sm transition hover:bg-muted/40"
                                >
                                    <Pencil size={13} />
                                    Edit Profil
                                </Link>
                            }
                        >
                            <ProfileInfoRow icon={BookOpen} label="NIS"           value={<span className="font-mono tracking-wider">{student.nis}</span>} />
                            <ProfileInfoRow icon={IdCard}   label="NIK"           value={student.nik} mono />
                            <ProfileInfoRow icon={User}     label="Nama Lengkap"  value={student.full_name} />
                            <ProfileInfoRow icon={Users}    label="Jenis Kelamin" value={student.gender === 'L' ? 'Laki-laki' : 'Perempuan'} />
                            <ProfileInfoRow icon={Home}     label="Tempat Lahir"  value={student.birth_place} />
                            <ProfileInfoRow
                                icon={CalendarDays}
                                label="Tanggal Lahir"
                                value={
                                    student.birth_date
                                        ? new Date(student.birth_date).toLocaleDateString('id-ID', {
                                              day: 'numeric',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : null
                                }
                            />
                            <ProfileInfoRow icon={Home}     label="Alamat"        value={student.address} />
                            <ProfileInfoRow icon={BookOpen} label="Kelas"         value={student.current_class?.name} />
                        </ProfileSectionCard>

                        {/* Data EMIS (kompak, hide yang kosong) */}
                        <EmProfileView emProfile={student.emis_profile} compact />

                        {/* Nilai Kitab */}
                        <ProfileSectionCard
                            icon={Star}
                            title="Nilai Kitab"
                            subtitle="Nilai akademik berdasarkan semester yang dipilih"
                            action={
                                <div className="flex items-end gap-2">
                                    <div className="grid gap-1">
                                        <Label className="text-xs">Semester</Label>
                                        <Select value={semesterId} onValueChange={changeSemester}>
                                            <SelectTrigger className="w-52 h-8 text-xs">
                                                <SelectValue placeholder="Pilih semester" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {semesters.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.academic_year?.name} - {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            }
                        >
                            {grades.length === 0 ? (
                                <div className="py-8 text-center text-sm text-muted-foreground">
                                    Belum ada nilai pada semester ini.
                                </div>
                            ) : (
                                <div className="overflow-x-auto py-2">
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
                                                    <td className="px-3 py-2 text-center">
                                                        <Badge variant="outline">{g.grade_letter}</Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        {avg && (
                                            <tfoot>
                                                <tr className="border-t-2 bg-muted/30">
                                                    <td className="px-3 py-2 font-semibold text-muted-foreground">Rata-rata</td>
                                                    <td className="px-3 py-2 text-center font-bold text-emerald-700">{avg}</td>
                                                    <td />
                                                </tr>
                                            </tfoot>
                                        )}
                                    </table>
                                </div>
                            )}
                        </ProfileSectionCard>

                        {/* Pelanggaran Terbaru */}
                        {recentViolations.length > 0 && (
                            <ProfileSectionCard
                                icon={CalendarDays}
                                title="Pelanggaran Terbaru"
                                subtitle="10 pelanggaran terakhir yang tercatat"
                            >
                                <div className="overflow-x-auto py-2">
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
                                                    <td className="px-3 py-2">
                                                        <Badge
                                                            variant={
                                                                v.violation_type?.category === 'berat'
                                                                    ? 'destructive'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {v.violation_type?.category}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2 text-center font-semibold text-red-600">
                                                        {v.violation_type?.points}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </ProfileSectionCard>
                        )}
                    </div>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}
