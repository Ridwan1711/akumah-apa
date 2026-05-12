import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { AcademicPeriod, BreadcrumbItem, ImportRun, PaginatedData, SchoolClass, Semester, Student } from '@/types';

type EnrollmentRow = {
    id: number;
    class_id: number;
    period_id: number;
    school_class?: Pick<SchoolClass, 'id' | 'name'>;
};

type StudentRow = Student & {
    class_enrollments?: EnrollmentRow[];
};

type PreviewSummary = {
    created: number;
    updated: number;
    cleared: number;
    skipped: number;
    failed: number;
};

type Props = {
    students: PaginatedData<StudentRow>;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    academicPeriods: AcademicPeriod[];
    selectedPeriodId: number;
    isSuperAdmin: boolean;
    filters: {
        search?: string;
        status?: string;
        class_id?: string;
    };
    importRuns: ImportRun[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Enroll Kelas Santri', href: '/admin/student-enrollments' },
];

const STATUS_CONFIG: Record<string, { label: string; color: string }> = {
    active:  { label: 'Aktif',  color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    alumni:  { label: 'Alumni', color: 'bg-sky-100 text-sky-700 border-sky-200' },
    keluar:  { label: 'Keluar', color: 'bg-amber-100 text-amber-700 border-amber-200' },
    wafat:   { label: 'Wafat',  color: 'bg-rose-100 text-rose-700 border-rose-200' },
};

const IMPORT_STATUS_CONFIG: Record<string, { label: string; color: string; dot: string }> = {
    processing: { label: 'Diproses',  color: 'text-amber-600',  dot: 'bg-amber-400 animate-pulse' },
    queued:     { label: 'Antrian',   color: 'text-blue-600',   dot: 'bg-blue-400 animate-pulse' },
    completed:  { label: 'Selesai',   color: 'text-emerald-600',dot: 'bg-emerald-400' },
    failed:     { label: 'Gagal',     color: 'text-rose-600',   dot: 'bg-rose-400' },
};

function StatusBadge({ status }: { status: string }) {
    const cfg = STATUS_CONFIG[status] ?? { label: status, color: 'bg-gray-100 text-gray-600 border-gray-200' };
    return (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${cfg.color}`}>
            {cfg.label}
        </span>
    );
}

function PreviewStat({ label, value, color }: { label: string; value: number; color: string }) {
    return (
        <div className={`flex flex-col items-center rounded-xl border p-3 ${color}`}>
            <span className="text-xl font-bold leading-none">{value}</span>
            <span className="mt-1 text-[10px] font-medium uppercase tracking-wider opacity-70">{label}</span>
        </div>
    );
}

export default function StudentEnrollmentsIndex({
    students,
    classes,
    academicPeriods,
    selectedPeriodId,
    isSuperAdmin,
    filters,
    importRuns,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [preview, setPreview] = useState<PreviewSummary | null>(null);
    const [isDragOver, setIsDragOver] = useState(false);
    const defaultPeriodId = String(selectedPeriodId || academicPeriods[0]?.id || '');

    const form = useForm({
        mode: 'assign',
        class_id: '',
        period_id: defaultPeriodId,
        student_ids: [] as number[],
    });

    useEffect(() => {
        if (form.data.period_id !== defaultPeriodId) {
            form.setData('period_id', defaultPeriodId);
        }
    }, [defaultPeriodId]);

    const importForm = useForm<{ file: File | null; strategy: 'skip' | 'update' }>({
        file: null,
        strategy: 'skip',
    });

    const selectedAllCurrentPage =
        students.data.length > 0 && selectedIds.length === students.data.length;

    const runningImports = useMemo(
        () => importRuns.filter((r) => r.status === 'processing' || r.status === 'queued').length,
        [importRuns],
    );

    const activePeriod = academicPeriods.find((p) => p.id === Number(form.data.period_id));

    function applyFilter(next: Partial<Props['filters']>) {
        router.get(
            '/admin/student-enrollments',
            { ...filters, ...next, search, period_id: form.data.period_id },
            { preserveState: true, preserveScroll: true },
        );
    }

    function toggleStudent(id: number, checked: boolean) {
        setSelectedIds((prev) =>
            checked ? [...new Set([...prev, id])] : prev.filter((item) => item !== id),
        );
    }

    function toggleSelectAllCurrentPage(checked: boolean) {
        setSelectedIds(checked ? students.data.map((s) => s.id) : []);
    }

    function handleClearAllEnrollments() {
        if (!isSuperAdmin) return;
        const msg =
            'Ini akan menghapus SEMUA baris enrollment santri × kelas (semua periode) dan mengosongkan kelas saat ini bagi santri yang terdaftar di enrollment tersebut. Lanjutkan?';
        if (!window.confirm(msg)) return;
        if (!window.confirm('Konfirmasi terakhir: tindakan ini tidak dapat dibatalkan.')) return;
        router.post(
            '/admin/student-enrollments/clear-all',
            { acknowledge: '1' },
            { preserveScroll: true },
        );
    }

    function runBulk(endpoint: string, mode: 'assign' | 'move' | 'clear') {
        if (selectedIds.length === 0) { alert('Pilih minimal satu santri.'); return; }
        if (mode !== 'clear' && !form.data.class_id) { alert('Pilih kelas tujuan terlebih dahulu.'); return; }

        form.transform((data) => ({ ...data, mode, student_ids: selectedIds }));
        form.post(endpoint, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIds([]); setPreview(null); },
        });
    }

    async function runPreview(mode: 'assign' | 'move' | 'clear') {
        if (selectedIds.length === 0) { alert('Pilih minimal satu santri.'); return; }
        if (mode !== 'clear' && !form.data.class_id) { alert('Pilih kelas tujuan terlebih dahulu.'); return; }

        const response = await fetch('/admin/student-enrollments/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                mode,
                class_id: mode === 'clear' ? null : form.data.class_id,
                period_id: form.data.period_id,
                student_ids: selectedIds,
            }),
        });

        if (!response.ok) { alert('Gagal memuat preview.'); return; }
        const data = await response.json();
        setPreview({
            created: data.created ?? 0,
            updated: data.updated ?? 0,
            cleared: data.cleared ?? 0,
            skipped: data.skipped ?? 0,
            failed:  data.failed  ?? 0,
        });
    }

    function submitImport(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/student-enrollments-import', {
            forceFormData: true,
            onSuccess: () => importForm.reset('file'),
        });
    }

    function handleDrop(e: React.DragEvent) {
        e.preventDefault();
        setIsDragOver(false);
        const file = e.dataTransfer.files?.[0];
        if (file) importForm.setData('file', file);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enroll Kelas Santri" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* ── Page header ── */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Enroll Kelas Santri"
                        description="Assign, pindah, atau hapus enrollment santri per periode akademik."
                    />
                    {activePeriod && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary ring-1 ring-primary/20 self-start sm:self-auto">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                            {activePeriod.academic_year?.name} &mdash; {activePeriod.semester?.name}
                        </span>
                    )}
                </div>

                <FlashMessage />

                {/* ── Filter & Aksi ── */}
                <Card className="border-0 shadow-sm ring-1 ring-border/60">
                    <CardHeader className="pb-4">
                        <div className="flex items-center gap-2">
                            {/* Icon */}
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                <svg className="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                </svg>
                            </div>
                            <div>
                                <CardTitle className="text-base">Filter & Aksi Bulk</CardTitle>
                                <CardDescription className="text-xs">Pilih periode, filter santri, lalu jalankan aksi.</CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* Filter row */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Periode Akademik</Label>
                                <Select
                                    value={form.data.period_id}
                                    onValueChange={(v) => {
                                        form.setData('period_id', v);
                                        router.get('/admin/student-enrollments', { ...filters, search, period_id: v }, { preserveScroll: true });
                                    }}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Pilih periode" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {academicPeriods.map((period) => (
                                            <SelectItem key={period.id} value={String(period.id)}>
                                                {period.academic_year?.name} - {period.semester?.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Cari Santri</Label>
                                <div className="relative">
                                    <svg className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                    <Input
                                        className="h-9 pl-8 text-sm"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilter({ search })}
                                        onBlur={() => applyFilter({ search })}
                                        placeholder="Nama atau NIS…"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Status</Label>
                                <Select
                                    value={filters.status ?? 'all'}
                                    onValueChange={(v) => applyFilter({ status: v === 'all' ? undefined : v })}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Semua status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua status</SelectItem>
                                        <SelectItem value="active">Aktif</SelectItem>
                                        <SelectItem value="alumni">Alumni</SelectItem>
                                        <SelectItem value="keluar">Keluar</SelectItem>
                                        <SelectItem value="wafat">Wafat</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Kelas Saat Ini</Label>
                                <Select
                                    value={filters.class_id ?? 'all'}
                                    onValueChange={(v) => applyFilter({ class_id: v === 'all' ? undefined : v })}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Semua kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua kelas</SelectItem>
                                        {classes.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Divider */}
                        <div className="h-px bg-border/60" />

                        {/* Bulk actions row */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1 space-y-1.5">
                                <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Kelas Tujuan</Label>
                                <Select value={form.data.class_id} onValueChange={(v) => form.setData('class_id', v)}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue placeholder="Pilih kelas tujuan…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classes.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {selectedIds.length > 0 && (
                                    <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                        {selectedIds.length} dipilih
                                    </span>
                                )}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="h-9 text-xs"
                                    onClick={() => runPreview('assign')}
                                >
                                    <svg className="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Preview
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-9 text-xs"
                                    onClick={() => runBulk('/admin/student-enrollments/bulk-assign', 'assign')}
                                    disabled={form.processing}
                                >
                                    {form.processing ? <Spinner className="mr-1.5 h-3.5 w-3.5" /> : (
                                        <svg className="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    )}
                                    Assign
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    className="h-9 text-xs"
                                    onClick={() => runBulk('/admin/student-enrollments/bulk-move', 'move')}
                                    disabled={form.processing}
                                >
                                    <svg className="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                    Move
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    className="h-9 text-xs"
                                    onClick={() => runBulk('/admin/student-enrollments/bulk-clear', 'clear')}
                                    disabled={form.processing}
                                >
                                    <svg className="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Clear
                                </Button>
                                {isSuperAdmin && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-9 border-destructive/70 text-xs font-semibold text-destructive hover:bg-destructive/10"
                                        onClick={handleClearAllEnrollments}
                                    >
                                        Kosongkan semua enrollment
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Preview result */}
                        {preview && (
                            <div className="rounded-xl border bg-muted/30 p-4">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Hasil Preview</p>
                                <div className="grid grid-cols-5 gap-2">
                                    <PreviewStat label="Created" value={preview.created} color="border-emerald-200 bg-emerald-50 text-emerald-700" />
                                    <PreviewStat label="Updated" value={preview.updated} color="border-sky-200 bg-sky-50 text-sky-700" />
                                    <PreviewStat label="Cleared" value={preview.cleared} color="border-amber-200 bg-amber-50 text-amber-700" />
                                    <PreviewStat label="Skipped" value={preview.skipped} color="border-slate-200 bg-slate-50 text-slate-600" />
                                    <PreviewStat label="Failed"  value={preview.failed}  color="border-rose-200 bg-rose-50 text-rose-700" />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ── Student table ── */}
                <Card className="border-0 shadow-sm ring-1 ring-border/60 overflow-hidden">
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                    <svg className="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <CardTitle className="text-base">Daftar Santri</CardTitle>
                                    <CardDescription className="text-xs">
                                        {students.total} santri ditemukan
                                        {activePeriod && ` · Periode: ${activePeriod.academic_year?.name} — ${activePeriod.semester?.name}`}
                                    </CardDescription>
                                </div>
                            </div>
                            {selectedIds.length > 0 && (
                                <button
                                    type="button"
                                    onClick={() => setSelectedIds([])}
                                    className="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                >
                                    Batal pilih
                                </button>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30">
                                        <th className="w-10 px-4 py-3">
                                            <input
                                                type="checkbox"
                                                className="h-4 w-4 rounded border-gray-300 text-primary accent-primary cursor-pointer"
                                                checked={selectedAllCurrentPage}
                                                onChange={(e) => toggleSelectAllCurrentPage(e.target.checked)}
                                            />
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">NIS</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Nama Santri</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kelas Aktif</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Enrollment Periode</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/50">
                                    {students.data.map((student) => {
                                        const periodEnrollment = student.class_enrollments?.[0];
                                        const isSelected = selectedIds.includes(student.id);
                                        return (
                                            <tr
                                                key={student.id}
                                                className={`group cursor-pointer transition-colors hover:bg-muted/40 ${isSelected ? 'bg-primary/5' : ''}`}
                                                onClick={() => toggleStudent(student.id, !isSelected)}
                                            >
                                                <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                                    <input
                                                        type="checkbox"
                                                        className="h-4 w-4 rounded border-gray-300 text-primary accent-primary cursor-pointer"
                                                        checked={isSelected}
                                                        onChange={(e) => toggleStudent(student.id, e.target.checked)}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{student.nis}</td>
                                                <td className="px-4 py-3 font-medium">{student.full_name}</td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={student.status} />
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {student.current_class?.name
                                                        ? <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-foreground">{student.current_class.name}</span>
                                                        : <span className="text-muted-foreground/50">—</span>
                                                    }
                                                </td>
                                                <td className="px-4 py-3">
                                                    {periodEnrollment?.school_class?.name
                                                        ? <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{periodEnrollment.school_class.name}</span>
                                                        : <span className="text-muted-foreground/50">—</span>
                                                    }
                                                </td>
                                            </tr>
                                        );
                                    })}

                                    {students.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-16 text-center">
                                                <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                                    <svg className="h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                                    </svg>
                                                    <p className="text-sm font-medium">Tidak ada data santri</p>
                                                    <p className="text-xs">Coba ubah filter pencarian.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Pagination links={students.links} from={students.from} to={students.to} total={students.total} />

                {/* ── Import section ── */}
                <Card className="border-0 shadow-sm ring-1 ring-border/60">
                    <CardHeader className="pb-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                    <svg className="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <CardTitle className="text-base">Import Enrollment</CardTitle>
                                    <CardDescription className="text-xs">
                                        Upload massal via file XLSX atau CSV.
                                        {runningImports > 0 && (
                                            <span className="ml-2 inline-flex items-center gap-1 text-amber-600">
                                                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400" />
                                                {runningImports} proses berjalan
                                            </span>
                                        )}
                                    </CardDescription>
                                </div>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* Upload form */}
                        <form onSubmit={submitImport} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {/* Drag-and-drop zone */}
                                <div
                                    className={`lg:col-span-2 relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors cursor-pointer ${
                                        isDragOver
                                            ? 'border-primary bg-primary/5'
                                            : importForm.data.file
                                            ? 'border-emerald-400 bg-emerald-50'
                                            : 'border-border hover:border-primary/50 hover:bg-muted/30'
                                    }`}
                                    onDragOver={(e) => { e.preventDefault(); setIsDragOver(true); }}
                                    onDragLeave={() => setIsDragOver(false)}
                                    onDrop={handleDrop}
                                    onClick={() => document.getElementById('enroll-file-input')?.click()}
                                >
                                    <input
                                        id="enroll-file-input"
                                        type="file"
                                        accept=".xlsx,.csv"
                                        className="hidden"
                                        onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                                    />
                                    {importForm.data.file ? (
                                        <>
                                            <svg className="mb-2 h-6 w-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p className="text-xs font-semibold text-emerald-700">{importForm.data.file.name}</p>
                                            <p className="mt-0.5 text-[10px] text-emerald-600">Klik untuk ganti file</p>
                                        </>
                                    ) : (
                                        <>
                                            <svg className="mb-2 h-6 w-6 text-muted-foreground/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            <p className="text-xs font-medium text-muted-foreground">Drag & drop atau klik untuk pilih file</p>
                                            <p className="mt-0.5 text-[10px] text-muted-foreground/60">XLSX, CSV</p>
                                        </>
                                    )}
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Strategi</Label>
                                    <Select value={importForm.data.strategy} onValueChange={(v: 'skip' | 'update') => importForm.setData('strategy', v)}>
                                        <SelectTrigger className="h-9">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="skip">Skip existing</SelectItem>
                                            <SelectItem value="update">Update existing</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex flex-col justify-end gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="h-9 w-full"
                                        disabled={importForm.processing || !importForm.data.file}
                                    >
                                        {importForm.processing ? <Spinner className="mr-2 h-3.5 w-3.5" /> : (
                                            <svg className="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                            </svg>
                                        )}
                                        Import
                                    </Button>
                                    <div className="flex gap-2">
                                        <Button type="button" size="sm" variant="outline" className="h-9 flex-1 text-xs" asChild>
                                            <a href={`/admin/student-enrollments-export?format=xlsx&period_id=${encodeURIComponent(form.data.period_id)}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(filters.status ?? '')}&class_id=${encodeURIComponent(filters.class_id ?? '')}`}>
                                                <svg className="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                Export
                                            </a>
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" className="h-9 flex-1 text-xs" asChild>
                                            <a href="/admin/student-enrollments-template?format=xlsx">
                                                <svg className="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                                Template
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        {/* Import run history */}
                        {importRuns.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Riwayat Import</p>
                                <div className="space-y-2">
                                    {importRuns.map((run) => {
                                        const cfg = IMPORT_STATUS_CONFIG[run.status] ?? { label: run.status, color: 'text-muted-foreground', dot: 'bg-gray-400' };
                                        return (
                                            <div
                                                key={run.id}
                                                className="flex items-center justify-between rounded-xl border bg-muted/20 px-4 py-3 transition-colors hover:bg-muted/40"
                                            >
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <span className={`h-2 w-2 flex-shrink-0 rounded-full ${cfg.dot}`} />
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">{run.file_name}</p>
                                                        <p className={`text-xs ${cfg.color}`}>
                                                            {cfg.label}
                                                            <span className="ml-2 text-muted-foreground">·</span>
                                                            <span className="ml-2 text-muted-foreground">{run.strategy}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="ml-3 flex flex-shrink-0 gap-2">
                                                    {run.error_report_path && (
                                                        <Button variant="outline" size="sm" className="h-7 text-xs" asChild>
                                                            <a href={`/admin/student-enrollments-import-errors/${run.uuid}`}>
                                                                <svg className="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                                                </svg>
                                                                Error CSV
                                                            </a>
                                                        </Button>
                                                    )}
                                                    {run.status === 'failed' && (
                                                        <Button
                                                            variant="secondary"
                                                            size="sm"
                                                            className="h-7 text-xs"
                                                            onClick={() => router.post(`/admin/student-enrollments-import-runs/${run.id}/retry`)}
                                                        >
                                                            <svg className="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                            </svg>
                                                            Retry
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 rounded-xl border border-dashed px-4 py-5 text-center">
                                <svg className="mx-auto h-6 w-6 text-muted-foreground/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p className="text-xs text-muted-foreground">Belum ada riwayat import enrollment.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}