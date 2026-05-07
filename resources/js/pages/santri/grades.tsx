import { Head, router } from '@inertiajs/react';
import { Award, BookOpenCheck, Filter, Inbox } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyyahScore, Semester } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Nilai Kitab', href: '/santri/grades' },
];

type Props = {
    student: { id: number; full_name: string; nis: string };
    semesters: (Semester & { academic_year?: { id: number; name: string } })[];
    grades: DiniyyahScore[];
    filters: { semester_id?: string };
};

export default function SantriGrades({ semesters, grades, filters }: Props) {
    const [semesterId, setSemesterId] = useState(filters.semester_id ?? '');

    function loadGrades() {
        if (semesterId) {
            router.get('/santri/grades', { semester_id: semesterId }, { preserveState: true });
        }
    }

    const avg = grades.length > 0
        ? (grades.reduce((acc, g) => acc + Number(g.score ?? 0), 0) / grades.length).toFixed(1)
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nilai Kitab" />
            
            <div className="min-h-full bg-muted/50 ">
                <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                    
                    {/* Header Section */}
                    <div className="mb-6 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-foreground  tracking-tight">
                                Nilai Kitab
                            </h1>
                            <p className="text-muted-foreground mt-1">
                                Pantau perkembangan akademik dan hasil evaluasi per semester.
                            </p>
                        </div>
                    </div>

                    {/* Filter Bar */}
                    <div className="mb-6 rounded-2xl border border-border bg-white p-5 shadow-sm transition-all">
                        <div className="flex flex-col sm:flex-row items-start sm:items-end gap-4">
                            <div className="flex-1 w-full grid gap-1.5">
                                <Label className=" text-[11px] font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <Filter size={14} className="text-emerald-600" />
                                    Pilih Semester
                                </Label>
                                <Select value={semesterId} onValueChange={setSemesterId}>
                                    <SelectTrigger className="h-11 rounded-xl border-border bg-muted/50  text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-full sm:max-w-md">
                                        <SelectValue placeholder="-- Pilih Tahun Ajaran & Semester --" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.academic_year?.name} — {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <button 
                                onClick={loadGrades}
                                disabled={!semesterId}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-sidebar px-6 py-2.5 h-11  text-sm font-semibold text-white shadow-md transition-all hover:bg-sidebar hover:shadow-lg active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none"
                            >
                                Tampilkan Data
                            </button>
                        </div>
                    </div>

                    {/* Content Section */}
                    {grades.length > 0 ? (
                        <div className="flex flex-col gap-6">
                            
                            {/* Average Score Highlight Card */}
                            {avg && (
                                <div className="flex items-center justify-between overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 px-6 py-5 shadow-md">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 text-white backdrop-blur-md">
                                            <Award size={24} strokeWidth={1.5} />
                                        </div>
                                        <div>
                                            <p className=" text-[11px] font-bold uppercase tracking-widest text-emerald-100">
                                                Rata-rata Nilai Semester
                                            </p>
                                            <p className=" text-3xl font-bold text-white mt-0.5">
                                                {avg}
                                            </p>
                                        </div>
                                    </div>
                                    {/* Decorative Icon */}
                                    <BookOpenCheck size={64} className="text-white opacity-10 mr-4 hidden sm:block" />
                                </div>
                            )}

                            {/* Grades Table */}
                            <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm whitespace-nowrap">
                                        <thead className="border-b border-border/70 bg-muted/40 text-muted-foreground">
                                            <tr>
                                                <th className="px-6 py-4 font-semibold ">Mata Pelajaran</th>
                                                <th className="px-6 py-4 text-center font-semibold  w-24">Nilai</th>
                                                <th className="px-6 py-4 text-center font-semibold  w-24">Huruf</th>
                                                <th className="px-6 py-4 font-semibold ">Catatan Asatidz</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/60">
                                            {grades.map((g) => (
                                                <tr key={g.id} className="transition-colors hover:bg-muted/50">
                                                    <td className="px-6 py-4 font-medium text-foreground">
                                                        {g.subject?.name}
                                                    </td>
                                                    <td className="px-6 py-4 text-center  text-2xl font-bold text-foreground tabular-nums">
                                                        {g.score}
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <Badge className="bg-muted text-foreground/90 hover:bg-muted border-none font-bold text-xs px-2.5 py-0.5 shadow-none">
                                                            {g.grade_letter}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-6 py-4 text-muted-foreground  text-sm max-w-xs truncate sm:max-w-none sm:whitespace-normal">
                                                        <span className="text-muted-foreground">—</span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    ) : (
                        /* Empty State */
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-white py-16 px-4 text-center shadow-sm">
                            <div className="rounded-full bg-muted/40 p-4 mb-4 border border-border/70 shadow-sm">
                                <Inbox size={32} className="text-muted-foreground" strokeWidth={1.5} />
                            </div>
                            <h3 className="text-2xl font-bold text-foreground ">
                                Belum Ada Nilai
                            </h3>
                            <p className=" text-sm text-muted-foreground max-w-md mt-2 leading-relaxed">
                                Silakan pilih semester pada menu di atas untuk melihat nilai, atau mungkin nilai untuk semester ini belum dipublikasikan oleh Asatidz.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}