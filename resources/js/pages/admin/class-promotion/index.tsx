import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, GraduationCap, RefreshCw, School, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudEmptyState,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ImportRun, PaginatedData, SchoolClass, Student, User } from '@/types';
import { toast } from 'sonner';

type StudentRow = Pick<Student, 'id' | 'nis' | 'full_name' | 'current_class_id'>;

type PromotionAction = 'promote' | 'stay' | 'graduate';

type PromotionRow = {
    student_id: number;
    action: PromotionAction;
    target_class_id: number | null;
};

type Props = {
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    students: PaginatedData<StudentRow> | null;
    filters: { source_class_id?: string; run_uploader_id?: string; per_page?: string };
    bulkRuns: ImportRun[];
    bulkUploaders: Pick<User, 'id' | 'name'>[];
    perPageOptions: number[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kenaikan Kelas', href: '/admin/class-promotion' },
];

export default function ClassPromotionIndex({
    classes,
    students,
    filters,
    bulkRuns,
    bulkUploaders,
    perPageOptions,
}: Props) {
    const [promotions, setPromotions] = useState<Record<number, PromotionRow>>({});
    const form = useForm<{ source_class_id: string; promotions: PromotionRow[] }>({
        source_class_id: filters.source_class_id ?? '',
        promotions: [],
    });

    const hasRunningJobs = useMemo(
        () => bulkRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [bulkRuns],
    );

    function setPerPage(value: string) {
        router.get(
            '/admin/class-promotion',
            { ...filters, per_page: value },
            { preserveState: true, preserveScroll: true },
        );
    }

    function setSourceClass(value: string) {
        router.get(
            '/admin/class-promotion',
            {
                ...filters,
                source_class_id: value || undefined,
                page: undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function setRunUploader(value: string) {
        router.get(
            '/admin/class-promotion',
            {
                ...filters,
                run_uploader_id: value === 'all' ? undefined : value,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function updatePromotion(studentId: number, patch: Partial<PromotionRow>) {
        setPromotions((prev) => {
            const current = prev[studentId] ?? {
                student_id: studentId,
                action: 'promote',
                target_class_id: null,
            };
            return {
                ...prev,
                [studentId]: { ...current, ...patch },
            };
        });
    }

    function submitPromotions() {
        const rows = Object.values(promotions).filter((item) => item.action !== 'promote' || item.target_class_id);
        if (rows.length === 0) {
            toast.error('Belum ada aksi kenaikan kelas yang dipilih');
            return;
        }
        if (!filters.source_class_id) {
            toast.error('Pilih kelas asal terlebih dahulu');
            return;
        }
        form.setData({
            source_class_id: filters.source_class_id,
            promotions: rows,
        });
        form.post('/admin/class-promotion', {
            onSuccess: () => {
                setPromotions({});
                toast.success('Proses kenaikan kelas dipindahkan ke background');
            },
            onError: () => toast.error('Gagal memulai proses kenaikan kelas'),
        });
    }

    function retryRun(runId: number) {
        router.post(`/admin/class-promotion/runs/${runId}/retry`, undefined, {
            onSuccess: () => toast.success('Retry diproses di background'),
            onError: () => toast.error('Gagal retry'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kenaikan Kelas" />
            <div>
                <CrudPageHeader
                    title="Kenaikan Kelas"
                    description="Atur promosi, tetap kelas, atau kelulusan santri secara massal."
                />

                <CrudStatStrip
                    items={[
                        { key: 'classes', label: 'Total Kelas', value: classes.length, icon: <School size={18} />, tone: 'blue' },
                        { key: 'students', label: 'Santri Kelas Dipilih', value: students?.total ?? 0, icon: <Users size={18} />, tone: 'green' },
                        { key: 'actions', label: 'Aksi Dipilih', value: Object.keys(promotions).length, icon: <GraduationCap size={18} />, tone: 'amber' },
                        { key: 'jobs', label: 'Job Berjalan', value: hasRunningJobs ? 'Ya' : 'Tidak', icon: <RefreshCw size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select
                                className="mcr-filter-select"
                                value={filters.source_class_id ?? ''}
                                onChange={(e) => setSourceClass(e.target.value)}
                            >
                                <option value="">Pilih kelas asal</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="mcr-filter-select"
                                value={filters.per_page ?? String(perPageOptions[0] ?? 25)}
                                onChange={(e) => setPerPage(e.target.value)}
                            >
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>
                                        {opt} / halaman
                                    </option>
                                ))}
                            </select>
                        </>
                    }
                    right={
                        <button
                            type="button"
                            className="mcr-btn primary"
                            onClick={submitPromotions}
                            disabled={form.processing}
                        >
                            <CheckCircle2 size={14} />
                            {form.processing ? 'Memproses...' : 'Proses Kenaikan'}
                        </button>
                    }
                />

                <CrudCard title="Daftar Santri" subtitle="Atur aksi per santri sebelum diproses.">
                    {!students ? (
                        <CrudEmptyState
                            title="Pilih kelas asal"
                            description="Tentukan kelas asal untuk menampilkan daftar santri aktif."
                        />
                    ) : (
                        <>
                            <CrudTableShell>
                                <table className="mcr-table">
                                    <thead>
                                        <tr>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Aksi</th>
                                            <th>Kelas Tujuan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {students.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={4}>
                                                    <CrudEmptyState
                                                        title="Tidak ada santri"
                                                        description="Tidak ditemukan santri aktif pada kelas ini."
                                                    />
                                                </td>
                                            </tr>
                                        ) : (
                                            students.data.map((student) => {
                                                const current = promotions[student.id] ?? {
                                                    student_id: student.id,
                                                    action: 'promote' as PromotionAction,
                                                    target_class_id: null,
                                                };
                                                return (
                                                    <tr key={student.id}>
                                                        <td>{student.nis}</td>
                                                        <td>{student.full_name}</td>
                                                        <td>
                                                            <select
                                                                className="mcr-form-select"
                                                                value={current.action}
                                                                onChange={(e) =>
                                                                    updatePromotion(student.id, {
                                                                        action: e.target.value as PromotionAction,
                                                                    })
                                                                }
                                                            >
                                                                <option value="promote">Naik Kelas</option>
                                                                <option value="stay">Tetap</option>
                                                                <option value="graduate">Lulus</option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select
                                                                className="mcr-form-select"
                                                                disabled={current.action !== 'promote'}
                                                                value={current.target_class_id ?? ''}
                                                                onChange={(e) =>
                                                                    updatePromotion(student.id, {
                                                                        target_class_id: e.target.value
                                                                            ? Number(e.target.value)
                                                                            : null,
                                                                    })
                                                                }
                                                            >
                                                                <option value="">Pilih kelas tujuan</option>
                                                                {classes.map((item) => (
                                                                    <option key={item.id} value={String(item.id)}>
                                                                        {item.name}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </CrudTableShell>
                            <CrudPagination links={students.links} />
                        </>
                    )}
                </CrudCard>

                <CrudCard
                    title="Riwayat Job Kenaikan"
                    subtitle="Status proses background kenaikan kelas."
                    right={
                        <select
                            className="mcr-filter-select"
                            value={filters.run_uploader_id ?? 'all'}
                            onChange={(e) => setRunUploader(e.target.value)}
                        >
                            <option value="all">Semua Uploader</option>
                            {bulkUploaders.map((uploader) => (
                                <option key={uploader.id} value={String(uploader.id)}>
                                    {uploader.name}
                                </option>
                            ))}
                        </select>
                    }
                >
                    {bulkRuns.length === 0 ? (
                        <CrudEmptyState
                            title="Belum ada job"
                            description="Riwayat job kenaikan kelas akan muncul di sini."
                        />
                    ) : (
                        bulkRuns.map((run) => (
                            <div key={run.id} className="mcr-run-item">
                                <div className="mcr-run-top">
                                    <div>
                                        <strong>{run.file_name}</strong>
                                        <div className="mcr-run-meta">{run.requestedBy?.name ?? 'System'}</div>
                                    </div>
                                    <div className="mcr-action-group">
                                        <span
                                            className={`mcr-dot-badge ${
                                                run.status === 'completed'
                                                    ? 'active'
                                                    : run.status === 'failed'
                                                      ? 'wafat'
                                                      : 'keluar'
                                            }`}
                                        >
                                            {run.status}
                                        </span>
                                        {run.status === 'failed' ? (
                                            <button
                                                type="button"
                                                className="mcr-btn secondary"
                                                onClick={() => retryRun(run.id)}
                                            >
                                                Retry
                                            </button>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="mcr-run-stats">
                                    <span>C:{run.created_count}</span>
                                    <span>U:{run.updated_count}</span>
                                    <span>S:{run.skipped_count}</span>
                                    <span>F:{run.failed_count}</span>
                                </div>
                            </div>
                        ))
                    )}
                </CrudCard>
            </div>
        </AppLayout>
    );
}
