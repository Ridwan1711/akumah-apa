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
import type { BreadcrumbItem, ImportRun, PaginatedData, SchoolClass, Semester, Student } from '@/types';

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
    classes: Pick<SchoolClass, 'id' | 'name' | 'level'>[];
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
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

export default function StudentEnrollmentsIndex({
    students,
    classes,
    semesters,
    selectedPeriodId,
    selectedSemesterId,
    filters,
    importRuns,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [preview, setPreview] = useState<PreviewSummary | null>(null);
    const defaultSemesterId = String(selectedSemesterId || semesters[0]?.id || '');

    const form = useForm({
        mode: 'assign',
        class_id: '',
        semester_id: defaultSemesterId,
        student_ids: [] as number[],
    });

    useEffect(() => {
        // Keep form state aligned with server-selected/default semester.
        if (form.data.semester_id !== defaultSemesterId) {
            form.setData('semester_id', defaultSemesterId);
        }
    }, [defaultSemesterId]);

    const importForm = useForm<{ file: File | null; strategy: 'skip' | 'update' }>({
        file: null,
        strategy: 'skip',
    });

    const selectedAllCurrentPage = students.data.length > 0 && selectedIds.length === students.data.length;

    const runningImports = useMemo(
        () => importRuns.filter((run) => run.status === 'processing' || run.status === 'queued').length,
        [importRuns],
    );

    function applyFilter(next: Partial<Props['filters']>) {
        router.get('/admin/student-enrollments', {
            ...filters,
            ...next,
            search,
            semester_id: form.data.semester_id,
        }, { preserveState: true, preserveScroll: true });
    }

    function toggleStudent(id: number, checked: boolean) {
        setSelectedIds((prev) => checked ? [...new Set([...prev, id])] : prev.filter((item) => item !== id));
    }

    function toggleSelectAllCurrentPage(checked: boolean) {
        if (!checked) {
            setSelectedIds([]);
            return;
        }
        setSelectedIds(students.data.map((student) => student.id));
    }

    function runBulk(endpoint: string, mode: 'assign' | 'move' | 'clear') {
        if (selectedIds.length === 0) {
            alert('Pilih minimal satu santri.');
            return;
        }
        if (mode !== 'clear' && !form.data.class_id) {
            alert('Pilih kelas tujuan terlebih dahulu.');
            return;
        }

        form.transform((data) => ({
            ...data,
            mode,
            student_ids: selectedIds,
        }));

        form.post(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedIds([]);
                setPreview(null);
            },
        });
    }

    async function runPreview(mode: 'assign' | 'move' | 'clear') {
        if (selectedIds.length === 0) {
            alert('Pilih minimal satu santri.');
            return;
        }
        if (mode !== 'clear' && !form.data.class_id) {
            alert('Pilih kelas tujuan terlebih dahulu.');
            return;
        }

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
                semester_id: form.data.semester_id,
                student_ids: selectedIds,
            }),
        });

        if (!response.ok) {
            alert('Gagal memuat preview.');
            return;
        }

        const data = await response.json();
        setPreview({
            created: data.created ?? 0,
            updated: data.updated ?? 0,
            cleared: data.cleared ?? 0,
            skipped: data.skipped ?? 0,
            failed: data.failed ?? 0,
        });
    }

    function submitImport(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/student-enrollments-import', {
            forceFormData: true,
            onSuccess: () => {
                importForm.reset('file');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enroll Kelas Santri" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Enroll Kelas Santri"
                    description="Assign/move/clear enrollment santri per periode dengan aksi massal."
                />
                <FlashMessage />

                <Card>
                    <CardHeader>
                        <CardTitle>Filter & Aksi Bulk</CardTitle>
                        <CardDescription>Pilih periode dulu, lalu pilih santri untuk diproses.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-4">
                            <div className="grid gap-1">
                                <Label>Periode</Label>
                                <Select
                                    value={form.data.semester_id}
                                    onValueChange={(v) => {
                                        form.setData('semester_id', v);
                                        router.get('/admin/student-enrollments', { ...filters, search, semester_id: v }, { preserveScroll: true });
                                    }}
                                >
                                    <SelectTrigger><SelectValue placeholder="Pilih periode" /></SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((semester) => (
                                            <SelectItem key={semester.id} value={String(semester.id)}>
                                                {semester.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Cari Santri</Label>
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onBlur={() => applyFilter({ search })}
                                    placeholder="Nama / NIS"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label>Status</Label>
                                <Select value={filters.status ?? 'all'} onValueChange={(v) => applyFilter({ status: v === 'all' ? undefined : v })}>
                                    <SelectTrigger><SelectValue placeholder="Semua status" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua status</SelectItem>
                                        <SelectItem value="active">Aktif</SelectItem>
                                        <SelectItem value="alumni">Alumni</SelectItem>
                                        <SelectItem value="keluar">Keluar</SelectItem>
                                        <SelectItem value="wafat">Wafat</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Kelas Saat Ini</Label>
                                <Select value={filters.class_id ?? 'all'} onValueChange={(v) => applyFilter({ class_id: v === 'all' ? undefined : v })}>
                                    <SelectTrigger><SelectValue placeholder="Semua kelas" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua kelas</SelectItem>
                                        {classes.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>
                                                {item.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-5">
                            <div className="md:col-span-2 grid gap-1">
                                <Label>Kelas Tujuan (untuk assign/move)</Label>
                                <Select value={form.data.class_id} onValueChange={(v) => form.setData('class_id', v)}>
                                    <SelectTrigger><SelectValue placeholder="Pilih kelas tujuan" /></SelectTrigger>
                                    <SelectContent>
                                        {classes.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>
                                                {item.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2 md:col-span-3">
                                <Button type="button" onClick={() => runPreview('assign')} variant="outline">Preview Assign</Button>
                                <Button type="button" onClick={() => runBulk('/admin/student-enrollments/bulk-assign', 'assign')} disabled={form.processing}>
                                    {form.processing ? <Spinner className="mr-2" /> : null}
                                    Bulk Assign
                                </Button>
                                <Button type="button" onClick={() => runBulk('/admin/student-enrollments/bulk-move', 'move')} variant="secondary" disabled={form.processing}>
                                    Bulk Move
                                </Button>
                                <Button type="button" onClick={() => runBulk('/admin/student-enrollments/bulk-clear', 'clear')} variant="destructive" disabled={form.processing}>
                                    Bulk Clear
                                </Button>
                            </div>
                        </div>

                        {preview && (
                            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                <strong>Preview:</strong> Created {preview.created}, Updated {preview.updated}, Cleared {preview.cleared}, Skipped {preview.skipped}, Failed {preview.failed}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Daftar Santri</CardTitle>
                        <CardDescription>Pilih santri yang akan di-assign. Semester aktif: {form.data.semester_id}</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/40">
                                    <tr>
                                        <th className="px-3 py-2 text-left">
                                            <input
                                                type="checkbox"
                                                checked={selectedAllCurrentPage}
                                                onChange={(e) => toggleSelectAllCurrentPage(e.target.checked)}
                                            />
                                        </th>
                                        <th className="px-3 py-2 text-left">NIS</th>
                                        <th className="px-3 py-2 text-left">Nama</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                        <th className="px-3 py-2 text-left">Kelas Saat Ini</th>
                                        <th className="px-3 py-2 text-left">Enrollment Periode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {students.data.map((student) => {
                                        const periodEnrollment = student.class_enrollments?.[0];
                                        return (
                                            <tr key={student.id} className="border-b last:border-0">
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedIds.includes(student.id)}
                                                        onChange={(e) => toggleStudent(student.id, e.target.checked)}
                                                    />
                                                </td>
                                                <td className="px-3 py-2">{student.nis}</td>
                                                <td className="px-3 py-2">{student.full_name}</td>
                                                <td className="px-3 py-2">
                                                    <Badge variant={student.status === 'active' ? 'default' : 'secondary'}>{student.status}</Badge>
                                                </td>
                                                <td className="px-3 py-2">{student.current_class?.name ?? '-'}</td>
                                                <td className="px-3 py-2">{periodEnrollment?.school_class?.name ?? '-'}</td>
                                            </tr>
                                        );
                                    })}
                                    {students.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">Tidak ada data.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                <Pagination links={students.links} from={students.from} to={students.to} total={students.total} />

                <Card>
                    <CardHeader>
                        <CardTitle>Import Enrollment</CardTitle>
                        <CardDescription>
                            Upload massal enrollment via file. Running import: {runningImports}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={submitImport} className="grid gap-3 md:grid-cols-4">
                            <div className="md:col-span-2">
                                <Label>File</Label>
                                <Input type="file" accept=".xlsx,.csv" onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)} />
                            </div>
                            <div>
                                <Label>Strategi</Label>
                                <Select value={importForm.data.strategy} onValueChange={(v: 'skip' | 'update') => importForm.setData('strategy', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="skip">Skip existing</SelectItem>
                                        <SelectItem value="update">Update existing</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <a
                                        href={`/admin/student-enrollments-export?format=xlsx&semester_id=${encodeURIComponent(form.data.semester_id)}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(filters.status ?? '')}&class_id=${encodeURIComponent(filters.class_id ?? '')}`}
                                    >
                                        Export
                                    </a>
                                </Button>
                                <Button type="submit" disabled={importForm.processing}>
                                    {importForm.processing ? <Spinner className="mr-2" /> : null}
                                    Import
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href="/admin/student-enrollments-template?format=xlsx">Template</a>
                                </Button>
                            </div>
                        </form>

                        <div className="space-y-2">
                            {importRuns.map((run) => (
                                <div key={run.id} className="rounded border p-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">{run.file_name}</div>
                                            <div className="text-xs text-muted-foreground">{run.status} · {run.strategy}</div>
                                        </div>
                                        <div className="flex gap-2">
                                            {run.error_report_path && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <a href={`/admin/student-enrollments-import-errors/${run.uuid}`}>Error CSV</a>
                                                </Button>
                                            )}
                                            {run.status === 'failed' && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => router.post(`/admin/student-enrollments-import-runs/${run.id}/retry`)}
                                                >
                                                    Retry
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {importRuns.length === 0 && (
                                <div className="text-sm text-muted-foreground">Belum ada riwayat import enrollment.</div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

