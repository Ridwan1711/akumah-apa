import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, FileStack, Info, Loader2, Plus, RefreshCw, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppSelect,
    CrudCard,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import type { AcademicYear, Auth, BreadcrumbItem, ImportRun, PaymentType, Student, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/admin/invoices' },
    { title: 'Bulk Generate', href: '/admin/invoices/generate' },
];

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const PREVIEW_URL = '/admin/invoices/bulk-generate-preview';

type BulkPreviewResponse = {
    target_student_count: number;
    would_skip_invoice_count?: number;
    students_without_formal_enrollment_count?: number;
    kuliah_without_tariff_count: number;
    summary: {
        payment_type_name: string;
        payment_type_code: string;
        academic_year_name: string;
        month_label: string | null;
        due_date: string;
        target_type: 'all' | 'selected';
        send_notification_for_existing: boolean;
    };
};

type Props = {
    paymentTypes: (Pick<PaymentType, 'id' | 'name' | 'code' | 'category' | 'default_breakdown'> & { is_recurring: boolean })[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    students: Pick<Student, 'id' | 'full_name' | 'nis'>[];
    bulkRuns: ImportRun[];
    bulkUploaders: Pick<User, 'id' | 'name'>[];
    runFilters: { run_uploader_id?: string };
};

function runStatusLabel(status: ImportRun['status']): string {
    const map: Record<ImportRun['status'], string> = {
        queued: 'Mengantri',
        processing: 'Memproses',
        completed: 'Selesai',
        failed: 'Gagal',
        cancelled: 'Dibatalkan',
    };

    return map[status] ?? status;
}

function runStatusBadgeClass(status: ImportRun['status']): string {
    if (status === 'completed') {
        return 'active';
    }
    if (status === 'failed') {
        return 'wafat';
    }

    return 'keluar';
}

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function InvoiceGenerate({
    paymentTypes,
    academicYears,
    students,
    bulkRuns,
    bulkUploaders,
    runFilters,
}: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canCreateInvoice = can(auth, 'invoice.create');

    const form = useForm({
        payment_type_id: '',
        academic_year_id: '',
        target_type: 'all' as 'all' | 'selected',
        student_ids: [] as number[],
        month: '' as string,
        due_date: '',
        send_notification_for_existing: true,
        breakdown: [] as Array<{ label: string; amount: string }>,
    });

    const [studentSearchQuery, setStudentSearchQuery] = useState('');
    const [lastRunsRefreshedAt, setLastRunsRefreshedAt] = useState<Date | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [previewData, setPreviewData] = useState<BulkPreviewResponse | null>(null);
    const [retryingRunId, setRetryingRunId] = useState<number | null>(null);

    const selectedPT = paymentTypes.find((pt) => String(pt.id) === form.data.payment_type_id);
    const hasRunningJobs = useMemo(
        () => bulkRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [bulkRuns],
    );

    const paymentTypeOptions = useMemo<SelectOption[]>(
        () => paymentTypes.map((pt) => ({ value: String(pt.id), label: `${pt.name} (${pt.code})` })),
        [paymentTypes],
    );

    const academicYearOptions = useMemo<SelectOption[]>(
        () => academicYears.map((ay) => ({ value: String(ay.id), label: ay.name })),
        [academicYears],
    );

    const monthOptions = useMemo<SelectOption[]>(
        () => monthNames.map((name, index) => ({ value: String(index + 1), label: name })),
        [],
    );

    const targetTypeOptions = useMemo<SelectOption[]>(
        () => [
            { value: 'all', label: 'Semua santri aktif' },
            { value: 'selected', label: 'Pilih santri tertentu' },
        ],
        [],
    );

    const visibleStudents = useMemo(() => {
        const q = studentSearchQuery.trim().toLowerCase();
        if (!q) {
            return students;
        }

        return students.filter((s) => {
            const name = (s.full_name ?? '').toLowerCase();
            const nis = String(s.nis ?? '').toLowerCase();

            return name.includes(q) || nis.includes(q);
        });
    }, [students, studentSearchQuery]);

    useEffect(() => {
        if (!hasRunningJobs) {
            return;
        }
        const timer = window.setInterval(() => {
            router.reload({ only: ['bulkRuns'] });
        }, 7000);

        return () => window.clearInterval(timer);
    }, [hasRunningJobs]);

    useEffect(() => {
        setLastRunsRefreshedAt(new Date());
    }, [bulkRuns]);

    const refreshBulkRuns = useCallback(() => {
        router.reload({ only: ['bulkRuns'], preserveUrl: true });
    }, []);

    const fetchPreview = useCallback(async () => {
        setPreviewLoading(true);
        setPreviewError(null);
        setPreviewData(null);

        const body: Record<string, unknown> = {
            payment_type_id: form.data.payment_type_id ? Number(form.data.payment_type_id) : '',
            academic_year_id: form.data.academic_year_id ? Number(form.data.academic_year_id) : '',
            target_type: form.data.target_type,
            student_ids: form.data.target_type === 'selected' ? form.data.student_ids : [],
            due_date: form.data.due_date,
            send_notification_for_existing: form.data.send_notification_for_existing,
            breakdown: form.data.breakdown
                .filter((item) => item.label.trim() !== '' && item.amount !== '')
                .map((item) => ({ label: item.label.trim(), amount: Number(item.amount) })),
        };

        if (selectedPT?.is_recurring && form.data.month) {
            body.month = Number(form.data.month);
        }

        try {
            const res = await fetch(PREVIEW_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });

            const json = (await res.json()) as BulkPreviewResponse & { message?: string; errors?: Record<string, string[]> };

            if (!res.ok) {
                if (json.errors) {
                    const first = Object.values(json.errors).flat()[0];

                    setPreviewError(first ?? json.message ?? 'Validasi gagal.');
                } else {
                    setPreviewError(json.message ?? 'Gagal memuat pratinjau.');
                }

                return;
            }

            setPreviewData(json as BulkPreviewResponse);
        } catch {
            setPreviewError('Gagal memuat pratinjau. Periksa koneksi lalu coba lagi.');
        } finally {
            setPreviewLoading(false);
        }
    }, [form.data, selectedPT?.is_recurring]);

    function openConfirmModal() {
        if (!canCreateInvoice) {
            return;
        }
        setConfirmOpen(true);
        void fetchPreview();
    }

    function closeConfirmModal() {
        if (form.processing) {
            return;
        }
        setConfirmOpen(false);
        setPreviewError(null);
        setPreviewData(null);
    }

    function confirmSubmit() {
        if (!canCreateInvoice) {
            return;
        }
        form.post('/admin/invoices/bulk-generate', {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setPreviewData(null);
            },
        });
    }

    function toggleStudent(studentId: number) {
        const ids = form.data.student_ids.includes(studentId)
            ? form.data.student_ids.filter((id) => id !== studentId)
            : [...form.data.student_ids, studentId];
        form.setData('student_ids', ids);
    }

    function selectAllVisibleStudents() {
        const visibleIds = visibleStudents.map((s) => s.id);
        const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => form.data.student_ids.includes(id));

        if (allVisibleSelected) {
            form.setData(
                'student_ids',
                form.data.student_ids.filter((id) => !visibleIds.includes(id)),
            );
        } else {
            form.setData('student_ids', Array.from(new Set([...form.data.student_ids, ...visibleIds])));
        }
    }

    function handleRunUploaderFilter(value: string) {
        router.get('/admin/invoices/generate', {
            run_uploader_id: value === 'all' ? undefined : value,
        }, { preserveScroll: true, preserveState: true });
    }

    function handleRetry(runId: number) {
        if (!canCreateInvoice) {
            return;
        }
        setRetryingRunId(runId);
        router.post(`/admin/invoices/bulk-runs/${runId}/retry`, {}, {
            preserveScroll: true,
            onFinish: () => setRetryingRunId(null),
        });
    }

    function getProgressPercent(run: ImportRun): number {
        if (!run.total_rows || run.total_rows <= 0) {
            return 0;
        }

        return Math.min(100, Math.round((run.processed_rows / run.total_rows) * 100));
    }

    const runsRefreshedLabel = lastRunsRefreshedAt
        ? lastRunsRefreshedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        : '—';

    function appendBreakdownRow() {
        form.setData('breakdown', [...form.data.breakdown, { label: '', amount: '' }]);
    }

    function removeBreakdownRow(index: number) {
        form.setData('breakdown', form.data.breakdown.filter((_, i) => i !== index));
    }

    function setBreakdownField(index: number, field: 'label' | 'amount', value: string) {
        form.setData('breakdown', form.data.breakdown.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bulk Generate Tagihan" />
            <div>
                <CrudPageHeader
                    title="Bulk Generate Tagihan"
                    description="Generate invoice massal berdasarkan jenis bayar, tahun ajaran, dan target santri."
                />

                <CrudStatStrip
                    items={[
                        { key: 'pt', label: 'Jenis Pembayaran Aktif', value: paymentTypes.length, icon: <FileStack size={18} />, tone: 'blue' },
                        { key: 'ay', label: 'Tahun Ajaran', value: academicYears.length, icon: <CalendarDays size={18} />, tone: 'green' },
                        { key: 'students', label: 'Santri Aktif', value: students.length, icon: <Users size={18} />, tone: 'amber' },
                        { key: 'jobs', label: 'Job Berjalan', value: hasRunningJobs ? 'Ya' : 'Tidak', icon: <RefreshCw size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudCard
                    title="Job Center"
                    subtitle="Pantau status bulk generate invoice."
                    right={(
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                            <span className="mcr-table-meta" title="Diperbarui otomatis saat polling atau tombol segarkan">
                                Terakhir dimuat: {runsRefreshedLabel}
                            </span>
                            <button type="button" className="mcr-btn ghost" onClick={refreshBulkRuns}>
                                <RefreshCw size={14} />
                                Segarkan
                            </button>
                            <select className="mcr-filter-select" value={runFilters.run_uploader_id ?? 'all'} onChange={(e) => handleRunUploaderFilter(e.target.value)}>
                                <option value="all">Semua uploader</option>
                                {bulkUploaders.map((u) => (
                                    <option key={u.id} value={String(u.id)}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                    )}
                >
                    {bulkRuns.length === 0 ? (
                        <CrudEmptyState title="Belum ada job" description="Riwayat bulk generate akan tampil di sini." />
                    ) : (
                        <>
                            {bulkRuns.map((run) => (
                                <div key={run.id} className="mcr-run-item">
                                    <div className="mcr-run-top">
                                        <div>
                                            <strong>{run.file_name}</strong>
                                            <div className="mcr-run-meta">{new Date(run.created_at).toLocaleString('id-ID')}</div>
                                        </div>
                                        <div className="mcr-action-group">
                                            <span className={`mcr-dot-badge ${runStatusBadgeClass(run.status)}`}>{runStatusLabel(run.status)}</span>
                                            {run.status === 'failed' && canCreateInvoice ? (
                                                <button
                                                    type="button"
                                                    className="mcr-btn secondary"
                                                    onClick={() => handleRetry(run.id)}
                                                    disabled={retryingRunId === run.id}
                                                >
                                                    {retryingRunId === run.id ? 'Memproses…' : 'Coba lagi'}
                                                </button>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className="mcr-run-stats">
                                        <span>{run.processed_rows}/{run.total_rows || '-'}</span>
                                        <span>{getProgressPercent(run)}%</span>
                                        <span title="Dibuat (invoice baru)">D:{run.created_count}</span>
                                        <span title="Diperbarui">P:{run.updated_count}</span>
                                        <span title="Dilewati">L:{run.skipped_count}</span>
                                        <span title="Gagal">G:{run.failed_count}</span>
                                    </div>
                                </div>
                            ))}
                            <p className="mcr-table-meta" style={{ marginTop: 10 }}>
                                <strong>Keterangan singkat:</strong>
                                {' '}
                                D = dibuat,
                                P = diperbarui,
                                L = dilewati,
                                G = gagal
                                {' '}
                                (sesuai penghitung job impor/bulk).
                            </p>
                        </>
                    )}
                </CrudCard>

                <CrudToolbar
                    left={<span className="mcr-table-meta">Isi parameter generate, lalu jalankan proses di background.</span>}
                />

                <CrudCard title="Form bulk generate">
                    {!canCreateInvoice ? (
                        <div className="mcr-confirm-note" style={{ marginBottom: 12 }}>
                            <AlertTriangle size={13} />
                            <span>Anda tidak punya izin membuat tagihan (invoice.create); tombol generate dinonaktifkan.</span>
                        </div>
                    ) : null}

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            openConfirmModal();
                        }}
                    >
                        <div className="mcr-form-grid">
                            <div className="mcr-form-group">
                                <label>Jenis pembayaran</label>
                                <AppSelect
                                    options={paymentTypeOptions}
                                    value={paymentTypeOptions.find((o) => String(o.value) === form.data.payment_type_id) ?? null}
                                    onChange={(opt) => {
                                        const nextId = opt ? String(opt.value) : '';
                                        const nextPt = paymentTypes.find((pt) => String(pt.id) === nextId);
                                        form.setData({
                                            ...form.data,
                                            payment_type_id: nextId,
                                            month: nextPt?.is_recurring ? form.data.month : '',
                                            breakdown: (nextPt?.default_breakdown ?? []).map((item) => ({
                                                label: item.label,
                                                amount: String(item.amount),
                                            })),
                                        });
                                    }}
                                    placeholder="Pilih jenis…"
                                    isDisabled={!canCreateInvoice}
                                />
                                <InputError message={form.errors.payment_type_id} />
                            </div>
                            <div className="mcr-form-group">
                                <label>Tahun ajaran</label>
                                <AppSelect
                                    options={academicYearOptions}
                                    value={academicYearOptions.find((o) => String(o.value) === form.data.academic_year_id) ?? null}
                                    onChange={(opt) => form.setData('academic_year_id', opt ? String(opt.value) : '')}
                                    placeholder="Pilih tahun…"
                                    isDisabled={!canCreateInvoice}
                                />
                                <InputError message={form.errors.academic_year_id} />
                            </div>

                            {selectedPT?.is_recurring ? (
                                <div className="mcr-form-group">
                                    <label>Bulan</label>
                                    <AppSelect
                                        options={monthOptions}
                                        value={monthOptions.find((o) => String(o.value) === form.data.month) ?? null}
                                        onChange={(opt) => form.setData('month', opt ? String(opt.value) : '')}
                                        placeholder="Pilih bulan…"
                                        isDisabled={!canCreateInvoice}
                                    />
                                    <InputError message={form.errors.month} />
                                </div>
                            ) : null}

                            <div className="mcr-form-group">
                                <label>Jatuh tempo</label>
                                <input type="date" className="mcr-input" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} disabled={!canCreateInvoice} />
                                <InputError message={form.errors.due_date} />
                            </div>

                            <div className="mcr-form-group">
                                <label>Target santri</label>
                                <AppSelect
                                    options={targetTypeOptions}
                                    value={targetTypeOptions.find((o) => String(o.value) === form.data.target_type) ?? null}
                                    onChange={(opt) => {
                                        const value = (opt?.value === 'selected' ? 'selected' : 'all') as 'all' | 'selected';
                                        form.setData('target_type', value);
                                        if (value === 'all') {
                                            form.setData('student_ids', []);
                                        }
                                    }}
                                    placeholder="Pilih target…"
                                    isDisabled={!canCreateInvoice}
                                />
                                <InputError message={form.errors.target_type} />
                            </div>

                            <div className="mcr-form-group full">
                                <label className="mcr-checkline">
                                    <input
                                        type="checkbox"
                                        checked={form.data.send_notification_for_existing}
                                        onChange={(e) => form.setData('send_notification_for_existing', e.target.checked)}
                                        disabled={!canCreateInvoice}
                                    />
                                    <span>Kirim ulang notifikasi (wali/santri) jika tagihan untuk kombinasi ini sudah ada</span>
                                </label>
                                <p className="mcr-table-meta" style={{ marginTop: 6, marginLeft: 24 }}>
                                    Jika tidak dicentang, duplikat biasanya dilewati tanpa notifikasi tambahan — sesuai perilaku job di server.
                                </p>
                            </div>
                        </div>

                        {form.data.target_type === 'selected' ? (
                            <div className="mcr-student-picker">
                                <div className="mcr-student-picker__head">
                                    <h3 className="mcr-student-picker__title">Pilih santri</h3>
                                    <span className="mcr-student-picker__chip">
                                        Dipilih:
                                        {' '}
                                        {form.data.student_ids.length}
                                        {' '}
                                        /
                                        {' '}
                                        {students.length}
                                    </span>
                                </div>
                                <p className="mcr-student-picker__meta">
                                    Menampilkan
                                    {' '}
                                    <strong>{visibleStudents.length}</strong>
                                    {' '}
                                    dari
                                    {' '}
                                    <strong>{students.length}</strong>
                                    {' '}
                                    santri
                                    {studentSearchQuery.trim() ? ' (sesuai pencarian)' : ''}
                                    .
                                </p>
                                <div className="mcr-student-picker__toolbar">
                                    <input
                                        type="search"
                                        className="mcr-input mcr-student-picker__search"
                                        placeholder="Cari nama atau NIS…"
                                        value={studentSearchQuery}
                                        onChange={(e) => setStudentSearchQuery(e.target.value)}
                                        disabled={!canCreateInvoice}
                                        autoComplete="off"
                                    />
                                    <button
                                        type="button"
                                        className="mcr-btn ghost"
                                        onClick={selectAllVisibleStudents}
                                        disabled={!canCreateInvoice || visibleStudents.length === 0}
                                    >
                                        {visibleStudents.length > 0 && visibleStudents.every((s) => form.data.student_ids.includes(s.id))
                                            ? 'Lepas tampilan'
                                            : 'Pilih semua tampilan'}
                                    </button>
                                </div>
                                {visibleStudents.length === 0 ? (
                                    <div className="mcr-student-picker__empty">
                                        Tidak ada santri yang cocok dengan pencarian. Ubah kata kunci atau kosongkan kolom cari.
                                    </div>
                                ) : (
                                    <div className="mcr-student-picker__list" role="group" aria-label="Daftar santri">
                                        {visibleStudents.map((student) => (
                                            <label key={student.id} className="mcr-student-picker__row">
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.student_ids.includes(student.id)}
                                                    onChange={() => toggleStudent(student.id)}
                                                    disabled={!canCreateInvoice}
                                                />
                                                <span className="mcr-student-picker__nameblock">
                                                    <span className="mcr-student-picker__name">{student.full_name}</span>
                                                    <span className="mcr-student-picker__nis">{student.nis}</span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                                <InputError message={form.errors.student_ids} />
                            </div>
                        ) : null}

                        <div className="mcr-student-picker" style={{ marginTop: 14 }}>
                            <div className="mcr-student-picker__head">
                                <h3 className="mcr-student-picker__title">Rincian Tagihan (opsional)</h3>
                                <button type="button" className="mcr-btn ghost" onClick={appendBreakdownRow}>
                                    <Plus size={14} />
                                    Tambah Item
                                </button>
                            </div>
                            <p className="mcr-student-picker__meta">
                                Bila diisi, rincian ini akan digunakan sebagai override untuk invoice yang di-generate.
                            </p>
                            {form.data.breakdown.length === 0 ? (
                                <div className="mcr-student-picker__empty">Belum ada item rincian.</div>
                            ) : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {form.data.breakdown.map((item, index) => (
                                        <div key={`bulk-breakdown-${index}`} style={{ display: 'grid', gridTemplateColumns: '1fr 180px auto', gap: 8 }}>
                                            <input
                                                type="text"
                                                className="mcr-input"
                                                value={item.label}
                                                onChange={(e) => setBreakdownField(index, 'label', e.target.value)}
                                                placeholder="Nama rincian"
                                            />
                                            <input
                                                type="number"
                                                min={0}
                                                className="mcr-input"
                                                value={item.amount}
                                                onChange={(e) => setBreakdownField(index, 'amount', e.target.value)}
                                                placeholder="Nominal"
                                            />
                                            <button type="button" className="mcr-btn ghost" onClick={() => removeBreakdownRow(index)}>
                                                Hapus
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <InputError message={form.errors.breakdown as string | undefined} />
                        </div>

                        <div style={{ marginTop: 14, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <button type="submit" className="mcr-btn primary" disabled={form.processing || !canCreateInvoice}>
                                <Plus size={14} />
                                {form.processing ? 'Memproses…' : 'Generate tagihan'}
                            </button>
                        </div>
                    </form>
                </CrudCard>

                <CrudModal
                    open={confirmOpen}
                    title="Konfirmasi bulk generate"
                    subtitle="Pastikan parameter sudah benar sebelum job diantrekan."
                    onClose={closeConfirmModal}
                    wide
                    footer={(
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, width: '100%' }}>
                            <button type="button" className="mcr-btn ghost" onClick={closeConfirmModal} disabled={form.processing}>
                                Batal
                            </button>
                            <button type="button" className="mcr-btn primary" onClick={confirmSubmit} disabled={form.processing || !!previewError || previewLoading || !previewData}>
                                {form.processing ? 'Mengantri…' : 'Ya, jalankan'}
                            </button>
                        </div>
                    )}
                >
                    {previewLoading ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 0' }}>
                            <Loader2 className="animate-spin" size={20} />
                            <span className="mcr-table-meta">Menghitung pratinjau…</span>
                        </div>
                    ) : null}

                    {previewError ? (
                        <div className="mcr-confirm-note" style={{ marginTop: previewLoading ? 12 : 0 }}>
                            <AlertTriangle size={13} />
                            <span>{previewError}</span>
                        </div>
                    ) : null}

                    {previewData ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                                <Info size={18} style={{ marginTop: 2, flexShrink: 0 }} />
                                <div>
                                    <p style={{ margin: 0, fontWeight: 600 }}>Ringkasan</p>
                                    <ul style={{ margin: '8px 0 0', paddingLeft: 18 }}>
                                        <li>
                                            Jenis:
                                            {' '}
                                            {previewData.summary.payment_type_name}
                                            {' '}
                                            (
                                            {previewData.summary.payment_type_code}
                                            )
                                        </li>
                                        <li>
                                            Tahun ajaran:
                                            {' '}
                                            {previewData.summary.academic_year_name}
                                        </li>
                                        {previewData.summary.month_label ? (
                                            <li>
                                                Bulan:
                                                {' '}
                                                {previewData.summary.month_label}
                                            </li>
                                        ) : null}
                                        <li>
                                            Jatuh tempo:
                                            {' '}
                                            {new Date(previewData.summary.due_date).toLocaleDateString('id-ID')}
                                        </li>
                                        <li>
                                            Target:
                                            {' '}
                                            {previewData.summary.target_type === 'all' ? 'Semua santri aktif' : 'Santri terpilih'}
                                        </li>
                                        <li>
                                            Notifikasi duplikat:
                                            {' '}
                                            {previewData.summary.send_notification_for_existing ? 'Ya (kirim ulang)' : 'Tidak'}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p style={{ margin: 0 }}>
                                <strong>Santri yang akan diproses (satu baris per santri):</strong>
                                {' '}
                                {previewData.target_student_count}
                            </p>
                            {previewData.students_without_formal_enrollment_count !== undefined ? (
                                <p className="mcr-table-meta" style={{ margin: 0 }}>
                                    Santri aktif tanpa enrollment tingkat formal untuk tahun ajaran ini (fallback tarif legacy):
                                    {' '}
                                    <strong>{previewData.students_without_formal_enrollment_count}</strong>
                                </p>
                            ) : null}
                            {(previewData.would_skip_invoice_count ?? previewData.kuliah_without_tariff_count) > 0 ? (
                                <p className="mcr-table-meta" style={{ margin: 0 }}>
                                    Perkiraan baris santri dilewati (tanpa nominal / aturan dinonaktifkan):
                                    {' '}
                                    <strong>{previewData.would_skip_invoice_count ?? previewData.kuliah_without_tariff_count}</strong>
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                </CrudModal>
            </div>
        </AppLayout>
    );
}
