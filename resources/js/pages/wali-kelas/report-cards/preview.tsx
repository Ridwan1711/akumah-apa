import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, FileDown, Save } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, KitabGrade, Semester, Student, StudentViolation, TahfidzProgress } from '@/types';

type ReportCardData = { id?: number; wali_kelas_notes: string | null; generated_at: string | null } | null;

type Props = {
    student: Student;
    semester: Semester;
    grades: KitabGrade[];
    tahfidzProgress: TahfidzProgress[];
    violations: StudentViolation[];
    reportCard: ReportCardData;
    avgScore: number | null;
    saveUrl: string;
    backUrl: string;
    pdfUrl: string;
    breadcrumbs: BreadcrumbItem[];
};

export default function WaliKelasReportPreview({
    student,
    semester,
    grades,
    tahfidzProgress,
    violations,
    reportCard,
    avgScore,
    saveUrl,
    backUrl,
    pdfUrl,
    breadcrumbs,
}: Props) {
    const form = useForm({
        student_id: student.id,
        semester_id: semester.id,
        wali_kelas_notes: reportCard?.wali_kelas_notes ?? '',
    });

    function handleSaveNotes(e: React.FormEvent) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        const classId = params.get('class_id') ?? '';
        const url = `${saveUrl}?class_id=${classId}&semester_id=${semester.id}`;
        form.post(url);
    }

    const totalViolationPoints = violations.reduce((sum, v) => sum + (v.violation_type?.points ?? 0), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Raport: ${student.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title={`Raport: ${student.full_name}`} description={`${semester.academic_year?.name} - ${semester.name}`} />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={backUrl}><ArrowLeft className="mr-1 size-4" /> Kembali</a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={pdfUrl} target="_blank" rel="noreferrer">
                                <FileDown className="mr-1 size-4" /> Download PDF
                            </a>
                        </Button>
                    </div>
                </div>
                <FlashMessage />

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-2 font-semibold">Data Santri</h3>
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                            <dt className="text-muted-foreground">Nama</dt><dd className="font-medium">{student.full_name}</dd>
                            <dt className="text-muted-foreground">NIS</dt><dd className="font-mono">{student.nis}</dd>
                            <dt className="text-muted-foreground">Kelas</dt><dd>{student.current_class?.name ?? '-'}</dd>
                        </dl>
                    </div>
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-2 font-semibold">Ringkasan</h3>
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                            <dt className="text-muted-foreground">Rata-rata Nilai</dt><dd className="font-bold text-lg">{avgScore ?? '-'}</dd>
                            <dt className="text-muted-foreground">Jumlah Mata Pelajaran</dt><dd>{grades.length}</dd>
                            <dt className="text-muted-foreground">Progress Tahfidz</dt><dd>{tahfidzProgress.length} entri</dd>
                            <dt className="text-muted-foreground">Poin Pelanggaran</dt><dd>{totalViolationPoints}</dd>
                        </dl>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h3 className="mb-3 font-semibold">Nilai Kitab</h3>
                    {grades.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Belum ada nilai untuk semester ini.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Mata Pelajaran</th>
                                    <th className="px-3 py-2 text-center font-medium w-20">Nilai</th>
                                    <th className="px-3 py-2 text-center font-medium w-16">Huruf</th>
                                    <th className="px-3 py-2 text-left font-medium">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {grades.map((g) => (
                                    <tr key={g.id} className="border-b last:border-0">
                                        <td className="px-3 py-2">{g.subject?.name}</td>
                                        <td className="px-3 py-2 text-center font-bold">{g.score}</td>
                                        <td className="px-3 py-2 text-center"><Badge variant="outline">{g.grade_letter}</Badge></td>
                                        <td className="px-3 py-2 text-muted-foreground">—</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {tahfidzProgress.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Progress Tahfidz</h3>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Juz</th>
                                    <th className="px-3 py-2 text-left font-medium">Surah</th>
                                    <th className="px-3 py-2 text-left font-medium">Ayat</th>
                                    <th className="px-3 py-2 text-left font-medium">Tipe</th>
                                    <th className="px-3 py-2 text-left font-medium">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tahfidzProgress.map((t) => (
                                    <tr key={t.id} className="border-b last:border-0">
                                        <td className="px-3 py-2">{t.juz}</td>
                                        <td className="px-3 py-2">{t.surah_to && t.surah_to !== t.surah_from ? `${t.surah_from} - ${t.surah_to}` : t.surah_from}</td>
                                        <td className="px-3 py-2">{t.ayat_from}-{t.ayat_to}</td>
                                        <td className="px-3 py-2"><Badge variant={t.type === 'ziyadah' ? 'default' : 'secondary'}>{t.type}</Badge></td>
                                        <td className="px-3 py-2">{t.grade}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {violations.length > 0 && (
                    <div className="rounded-xl border p-4">
                        <h3 className="mb-3 font-semibold">Pelanggaran ({totalViolationPoints} poin)</h3>
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
                                {violations.map((v) => (
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

                <div className="rounded-xl border p-4">
                    <h3 className="mb-3 font-semibold">Catatan Wali Kelas</h3>
                    <form onSubmit={handleSaveNotes} className="space-y-3">
                        <Textarea
                            value={form.data.wali_kelas_notes}
                            onChange={(e) => form.setData('wali_kelas_notes', e.target.value)}
                            placeholder="Tulis catatan wali kelas..."
                            rows={3}
                        />
                        <Button type="submit" size="sm" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            <Save className="mr-1 size-4" /> Simpan Catatan
                        </Button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
