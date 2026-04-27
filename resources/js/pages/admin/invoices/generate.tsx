import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, FileStack, Plus, RefreshCw, Users } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudEmptyState,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import { can } from '@/lib/authz';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, Auth, BreadcrumbItem, ImportRun, PaymentType, Student, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/admin/invoices' },
    { title: 'Bulk Generate', href: '/admin/invoices/generate' },
];

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

type Props = {
    paymentTypes: (Pick<PaymentType, 'id' | 'name' | 'code' | 'category'> & { is_recurring: boolean })[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    students: Pick<Student, 'id' | 'full_name' | 'nis'>[];
    bulkRuns: ImportRun[];
    bulkUploaders: Pick<User, 'id' | 'name'>[];
    runFilters: { run_uploader_id?: string };
};

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
    });

    const selectedPT = paymentTypes.find((pt) => String(pt.id) === form.data.payment_type_id);
    const hasRunningJobs = useMemo(
        () => bulkRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [bulkRuns],
    );

    useEffect(() => {
        if (!hasRunningJobs) return;
        const timer = window.setInterval(() => {
            router.reload({ only: ['bulkRuns'] });
        }, 7000);
        return () => window.clearInterval(timer);
    }, [hasRunningJobs]);

    function toggleStudent(studentId: number) {
        const ids = form.data.student_ids.includes(studentId)
            ? form.data.student_ids.filter((id) => id !== studentId)
            : [...form.data.student_ids, studentId];
        form.setData('student_ids', ids);
    }

    function selectAllStudents() {
        form.setData('student_ids', form.data.student_ids.length === students.length ? [] : students.map((s) => s.id));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!canCreateInvoice) return;
        form.post('/admin/invoices/bulk-generate');
    }

    function handleRunUploaderFilter(value: string) {
        router.get('/admin/invoices/generate', {
            run_uploader_id: value === 'all' ? undefined : value,
        }, { preserveScroll: true, preserveState: true });
    }

    function handleRetry(runId: number) {
        if (!canCreateInvoice) return;
        router.post(`/admin/invoices/bulk-runs/${runId}/retry`);
    }

    function getProgressPercent(run: ImportRun): number {
        if (!run.total_rows || run.total_rows <= 0) return 0;
        return Math.min(100, Math.round((run.processed_rows / run.total_rows) * 100));
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
                    right={
                        <select className="mcr-filter-select" value={runFilters.run_uploader_id ?? 'all'} onChange={(e) => handleRunUploaderFilter(e.target.value)}>
                            <option value="all">Semua Uploader</option>
                            {bulkUploaders.map((u) => (
                                <option key={u.id} value={String(u.id)}>{u.name}</option>
                            ))}
                        </select>
                    }
                >
                    {bulkRuns.length === 0 ? (
                        <CrudEmptyState title="Belum ada job" description="Riwayat bulk generate akan tampil di sini." />
                    ) : (
                        bulkRuns.map((run) => (
                            <div key={run.id} className="mcr-run-item">
                                <div className="mcr-run-top">
                                    <div>
                                        <strong>{run.file_name}</strong>
                                        <div className="mcr-run-meta">{new Date(run.created_at).toLocaleString('id-ID')}</div>
                                    </div>
                                    <div className="mcr-action-group">
                                        <span className={`mcr-dot-badge ${run.status === 'completed' ? 'active' : run.status === 'failed' ? 'wafat' : 'keluar'}`}>{run.status}</span>
                                        {run.status === 'failed' && canCreateInvoice ? (
                                            <button type="button" className="mcr-btn secondary" onClick={() => handleRetry(run.id)}>Retry</button>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="mcr-run-stats">
                                    <span>{run.processed_rows}/{run.total_rows || '-'}</span>
                                    <span>{getProgressPercent(run)}%</span>
                                    <span>C:{run.created_count}</span>
                                    <span>U:{run.updated_count}</span>
                                    <span>S:{run.skipped_count}</span>
                                    <span>F:{run.failed_count}</span>
                                </div>
                            </div>
                        ))
                    )}
                </CrudCard>

                <CrudToolbar
                    left={<span className="mcr-table-meta">Isi parameter generate, lalu jalankan proses di background.</span>}
                />

                <CrudCard title="Form Bulk Generate">
                    <form onSubmit={handleSubmit}>
                        <div className="mcr-form-grid">
                            <div className="mcr-form-group">
                                <label>Jenis Pembayaran</label>
                                <select className="mcr-form-select" value={form.data.payment_type_id} onChange={(e) => form.setData('payment_type_id', e.target.value)}>
                                    <option value="">Pilih jenis</option>
                                    {paymentTypes.map((pt) => (
                                        <option key={pt.id} value={String(pt.id)}>{pt.name} ({pt.code})</option>
                                    ))}
                                </select>
                                <InputError message={form.errors.payment_type_id} />
                            </div>
                            <div className="mcr-form-group">
                                <label>Tahun Ajaran</label>
                                <select className="mcr-form-select" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                                    <option value="">Pilih tahun</option>
                                    {academicYears.map((ay) => (
                                        <option key={ay.id} value={String(ay.id)}>{ay.name}</option>
                                    ))}
                                </select>
                                <InputError message={form.errors.academic_year_id} />
                            </div>

                            {selectedPT?.is_recurring ? (
                                <div className="mcr-form-group">
                                    <label>Bulan</label>
                                    <select className="mcr-form-select" value={form.data.month} onChange={(e) => form.setData('month', e.target.value)}>
                                        <option value="">Pilih bulan</option>
                                        {monthNames.map((name, index) => (
                                            <option key={name} value={String(index + 1)}>{name}</option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.month} />
                                </div>
                            ) : null}

                            <div className="mcr-form-group">
                                <label>Jatuh Tempo</label>
                                <input type="date" className="mcr-input" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                                <InputError message={form.errors.due_date} />
                            </div>

                            <div className="mcr-form-group">
                                <label>Target Santri</label>
                                <select
                                    className="mcr-form-select"
                                    value={form.data.target_type}
                                    onChange={(e) => {
                                        const value = e.target.value as 'all' | 'selected';
                                        form.setData('target_type', value);
                                        if (value === 'all') form.setData('student_ids', []);
                                    }}
                                >
                                    <option value="all">Semua Santri Aktif</option>
                                    <option value="selected">Pilih Santri Tertentu</option>
                                </select>
                                <InputError message={form.errors.target_type} />
                            </div>

                            <div className="mcr-form-group full">
                                <label className="mcr-checkline">
                                    <input
                                        type="checkbox"
                                        checked={form.data.send_notification_for_existing}
                                        onChange={(e) => form.setData('send_notification_for_existing', e.target.checked)}
                                    />
                                    <span>Kirim notifikasi walau invoice sudah ada</span>
                                </label>
                            </div>
                        </div>

                        {form.data.target_type === 'selected' ? (
                            <div style={{ marginTop: 12 }}>
                                <div className="mcr-table-meta" style={{ marginBottom: 8 }}>Pilih Santri</div>
                                <button type="button" className="mcr-btn ghost" onClick={selectAllStudents}>
                                    {form.data.student_ids.length === students.length ? 'Batal Semua' : 'Pilih Semua'}
                                </button>
                                <div className="mcr-select-list">
                                    {students.map((student) => (
                                        <label key={student.id} className="mcr-checkline">
                                            <input
                                                type="checkbox"
                                                checked={form.data.student_ids.includes(student.id)}
                                                onChange={() => toggleStudent(student.id)}
                                            />
                                            <span>{student.full_name} ({student.nis})</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={form.errors.student_ids} />
                            </div>
                        ) : null}

                        <div style={{ marginTop: 14, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <button type="submit" className="mcr-btn primary" disabled={form.processing || !canCreateInvoice}>
                                <Plus size={14} />
                                {form.processing ? 'Memproses...' : 'Generate Tagihan'}
                            </button>
                        </div>
                    </form>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
