import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Bell, Download, Eye, FilePlus2, FileUp, Search, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
    openDownload,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import { InvoiceSendReminderModal } from '@/pages/admin/invoices/invoice-send-reminder-modal';
import type {
    AcademicYear,
    Auth,
    BreadcrumbItem,
    ImportRun,
    PaginatedData,
    PaymentType,
    SchoolClass,
    StudentInvoiceGroup,
    TingkatSekolahFormal,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/admin/invoices' },
];

const statusLabels: Record<string, string> = {
    pending: 'Belum Bayar',
    paid: 'Lunas',
    partial: 'Sebagian',
    overdue: 'Jatuh Tempo',
    cancelled: 'Dibatalkan',
};

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

function firstRemindableInvoiceId(group: StudentInvoiceGroup): number | null {
    const row = group.invoices.find((i) => i.status !== 'paid' && i.status !== 'cancelled');
    return row ? row.id : null;
}

function invoiceExportSearchParams(filters: Record<string, string | undefined>): string {
    const p = new URLSearchParams();
    ['status', 'payment_type_id', 'academic_year_id', 'month', 'search', 'class_id', 'tingkat_sekolah_id', 'division_code'].forEach((key) => {
        const v = filters[key];
        if (v) {
            p.set(key, v);
        }
    });
    return p.toString();
}

function importRunStatusLabel(status: ImportRun['status']): string {
    const map: Record<string, string> = {
        queued: 'Antre',
        processing: 'Memproses',
        completed: 'Selesai',
        failed: 'Gagal',
        cancelled: 'Dibatalkan',
    };
    return map[status] ?? status;
}

type Props = {
    studentGroups: PaginatedData<StudentInvoiceGroup>;
    totalInvoiceCount: number;
    paymentTypes: Pick<PaymentType, 'id' | 'name' | 'code'>[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    classes: Pick<SchoolClass, 'id' | 'name'>[];
    tingkatSekolahs: Pick<TingkatSekolahFormal, 'id' | 'name' | 'code' | 'group'>[];
    divisionOptions: string[];
    filters: Record<string, string | undefined>;
    statusCounts: Record<string, number>;
    invoiceImportRuns?: ImportRun[];
};

export default function InvoiceIndex({
    studentGroups,
    totalInvoiceCount,
    paymentTypes,
    academicYears,
    classes,
    tingkatSekolahs,
    divisionOptions,
    filters,
    statusCounts,
    invoiceImportRuns = [],
}: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canCreateInvoice = can(auth, 'invoice.create');
    const canSendReminder = can(auth, 'invoice.reminder.send');
    const [reminderTarget, setReminderTarget] = useState<{ invoiceId: number; studentName: string } | null>(null);
    const [importOpen, setImportOpen] = useState(false);

    const importForm = useForm<{ file: File | null; strategy: 'skip' | 'update' }>({
        file: null,
        strategy: 'skip',
    });

    const hasRunningImport = useMemo(
        () => invoiceImportRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [invoiceImportRuns],
    );

    useEffect(() => {
        if (!hasRunningImport) {
            return undefined;
        }
        const id = window.setInterval(() => {
            router.reload({ only: ['invoiceImportRuns'] });
        }, 5000);
        return () => window.clearInterval(id);
    }, [hasRunningImport]);

    function handleFilter(key: string, value: string) {
        router.get(
            '/admin/invoices',
            { ...filters, [key]: value || undefined, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleImportSubmit(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/invoices-import', {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Import tagihan dimasukkan ke antrean');
            },
            onError: () => toast.error('Gagal mengunggah file import'),
        });
    }

    function handleRetryImport(runId: number) {
        router.post(
            `/admin/invoices-import-runs/${runId}/retry`,
            {},
            {
                onSuccess: () => toast.success('Retry import dimasukkan ke antrean'),
                onError: () => toast.error('Gagal mengulang import'),
            },
        );
    }

    const exportQuery = invoiceExportSearchParams(filters);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tagihan" />
            <FlashMessage />
            <div>
                <CrudPageHeader
                    title="Tagihan Santri"
                    description="Pantau tagihan, status pembayaran, dan detail invoice santri."
                />

                <CrudStatStrip
                    items={[
                        { key: 'all', label: 'Total Tagihan', value: statusCounts.all ?? totalInvoiceCount, icon: <Wallet size={18} />, tone: 'blue' },
                        { key: 'pending', label: 'Belum Bayar', value: statusCounts.pending ?? 0, icon: <Wallet size={18} />, tone: 'amber' },
                        { key: 'paid', label: 'Lunas', value: statusCounts.paid ?? 0, icon: <Wallet size={18} />, tone: 'green' },
                        { key: 'overdue', label: 'Jatuh Tempo', value: statusCounts.overdue ?? 0, icon: <Wallet size={18} />, tone: 'purple' },
                    ]}
                />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input value={filters.search ?? ''} placeholder="Cari nama/NIS..." onChange={(e) => handleFilter('search', e.target.value)} />
                            </div>
                            <select className="mcr-filter-select" value={filters.status ?? ''} onChange={(e) => handleFilter('status', e.target.value)}>
                                <option value="">Semua Status</option>
                                {Object.entries(statusLabels).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.payment_type_id ?? ''} onChange={(e) => handleFilter('payment_type_id', e.target.value)}>
                                <option value="">Semua Jenis</option>
                                {paymentTypes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.academic_year_id ?? ''} onChange={(e) => handleFilter('academic_year_id', e.target.value)}>
                                <option value="">Semua Tahun</option>
                                {academicYears.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.month ?? ''} onChange={(e) => handleFilter('month', e.target.value)}>
                                <option value="">Semua Bulan</option>
                                {monthNames.slice(1).map((label, idx) => (
                                    <option key={label} value={String(idx + 1)}>{label}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.class_id ?? ''} onChange={(e) => handleFilter('class_id', e.target.value)}>
                                <option value="">Semua Kelas</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.tingkat_sekolah_id ?? ''} onChange={(e) => handleFilter('tingkat_sekolah_id', e.target.value)}>
                                <option value="">Tingkat formal (semua)</option>
                                {tingkatSekolahs.map((item) => (
                                    <option key={item.id} value={String(item.id)}>
                                        {item.group ? `${item.name} (${item.group})` : item.name}
                                    </option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.division_code ?? ''} onChange={(e) => handleFilter('division_code', e.target.value)}>
                                <option value="">Semua Divisi Pengurus</option>
                                {divisionOptions.map((code) => (
                                    <option key={code} value={code}>{code}</option>
                                ))}
                            </select>
                        </>
                    }
                    right={
                        <>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload('/admin/invoices-template?format=xlsx')}
                            >
                                <Download size={14} />
                                Template
                            </button>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() =>
                                    openDownload(
                                        `/admin/invoices-export?format=xlsx${exportQuery ? `&${exportQuery}` : ''}`,
                                    )
                                }
                            >
                                <Download size={14} />
                                Export
                            </button>
                            {canCreateInvoice ? (
                                <button type="button" className="mcr-btn secondary" onClick={() => setImportOpen(true)}>
                                    <FileUp size={14} />
                                    Import
                                </button>
                            ) : null}
                            {canCreateInvoice ? (
                                <Link href="/admin/invoices/generate" className="mcr-btn primary">
                                    <FilePlus2 size={14} />
                                    Bulk Generate
                                </Link>
                            ) : null}
                        </>
                    }
                />

                {canCreateInvoice ? (
                    <div className="mb-4">
                        <CrudCard>
                        <div className="mcr-section-title">Riwayat import file</div>
                        {invoiceImportRuns.length === 0 ? (
                            <CrudEmptyState
                                title="Belum ada import"
                                description="Unggah file Excel lewat tombol Import untuk membuat tagihan per baris."
                            />
                        ) : (
                            <div className="space-y-3">
                                {invoiceImportRuns.map((run) => (
                                    <div key={run.id} className="rounded-md border px-3 py-2 text-sm">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <span className="font-medium">{run.file_name}</span>
                                                <span className="ml-2 text-muted-foreground">
                                                    {importRunStatusLabel(run.status)}
                                                </span>
                                                {run.requestedBy?.name ? (
                                                    <span className="ml-2 text-muted-foreground">• {run.requestedBy.name}</span>
                                                ) : null}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {run.status === 'failed' ? (
                                                    <button type="button" className="mcr-btn ghost text-xs" onClick={() => handleRetryImport(run.id)}>
                                                        Retry
                                                    </button>
                                                ) : null}
                                                {run.error_report_path ? (
                                                    <a
                                                        className="mcr-btn ghost text-xs"
                                                        href={`/admin/invoices-import-errors/${run.uuid}`}
                                                    >
                                                        Unduh error CSV
                                                    </a>
                                                ) : null}
                                            </div>
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Baris: {run.processed_rows}/{run.total_rows} • dibuat {run.created_count} • dilewati {run.skipped_count} • gagal {run.failed_count}
                                        </div>
                                        {run.error_message ? (
                                            <div className="mt-1 text-xs text-destructive">{run.error_message}</div>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                        </CrudCard>
                    </div>
                ) : null}

                <CrudCard>
                    {studentGroups.data.length === 0 ? (
                        <CrudEmptyState title="Tidak ada tagihan" description="Belum ada invoice sesuai filter saat ini." />
                    ) : (
                        <div className="space-y-4">
                            <div className="text-sm text-muted-foreground">
                                Menampilkan <strong>{studentGroups.data.length}</strong> dari <strong>{studentGroups.total}</strong> santri
                                {' '}(total <strong>{totalInvoiceCount}</strong> tagihan).
                            </div>
                            {studentGroups.data.map((group) => (
                                <div key={String(group.student_id ?? group.student_nis)} className="rounded-md border">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                                        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                            <div className="min-w-0">
                                                <div className="font-medium">{group.student_name}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    NIS: {group.student_nis}
                                                    {group.class_name ? ` • Diniyyah: ${group.class_name}` : ''}
                                                    {group.tingkat_formal_name ? ` • Formal: ${group.tingkat_formal_name}` : ''}
                                                </div>
                                            </div>
                                            {canSendReminder && firstRemindableInvoiceId(group) !== null ? (
                                                <button
                                                    type="button"
                                                    className="mcr-btn secondary shrink-0"
                                                    title="Kirim pengingat (semua tagihan terbuka santri)"
                                                    onClick={() => {
                                                        const id = firstRemindableInvoiceId(group);
                                                        if (id !== null) {
                                                            setReminderTarget({ invoiceId: id, studentName: group.student_name });
                                                        }
                                                    }}
                                                >
                                                    <Bell size={14} />
                                                    Kirim pengingat
                                                </button>
                                            ) : null}
                                        </div>
                                        <div className="text-right text-xs">
                                            <div><strong>{group.invoice_count}</strong> tagihan</div>
                                            <div className="text-muted-foreground">
                                                Total {formatCurrency(group.total_amount)} • Sisa {formatCurrency(group.total_remaining)}
                                            </div>
                                        </div>
                                    </div>
                                    <CrudTableShell>
                                        <table className="mcr-table">
                                            <thead>
                                                <tr>
                                                    <th>No. Invoice</th>
                                                    <th>Jenis Bayar</th>
                                                    <th>Periode</th>
                                                    <th>Nominal</th>
                                                    <th>Sisa</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {group.invoices.map((invoice) => (
                                                    <tr key={invoice.id}>
                                                        <td>{invoice.invoice_number}</td>
                                                        <td>{invoice.payment_type?.name ?? '-'} ({invoice.payment_type?.code ?? '-'})</td>
                                                        <td>{invoice.academic_year?.name ?? '-'}{invoice.month ? ` • ${monthNames[invoice.month]}` : ''}</td>
                                                        <td>{formatCurrency(Number(invoice.final_amount))}</td>
                                                        <td>{invoice.status === 'paid' ? '-' : formatCurrency(Number(invoice.remaining ?? invoice.final_amount))}</td>
                                                        <td>
                                                            <span className={`mcr-dot-badge ${invoice.status === 'paid' ? 'active' : invoice.status === 'overdue' ? 'wafat' : 'alumni'}`}>
                                                                {statusLabels[invoice.status] ?? invoice.status}
                                                            </span>
                                                            {(invoice.payments_count ?? 0) > 1 ? (
                                                                <span className="ml-2 text-xs text-muted-foreground">
                                                                    Cicilan ke-{invoice.payments_count}
                                                                </span>
                                                            ) : null}
                                                        </td>
                                                        <td>
                                                            <div className="mcr-action-group">
                                                                <Link href={`/admin/invoices/${invoice.id}`} className="mcr-icon-action" title="Detail">
                                                                    <Eye size={13} />
                                                                </Link>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </CrudTableShell>
                                </div>
                            ))}
                        </div>
                    )}
                    <CrudPagination links={studentGroups.links} />
                </CrudCard>

                {reminderTarget ? (
                    <InvoiceSendReminderModal
                        open
                        onClose={() => setReminderTarget(null)}
                        invoiceId={reminderTarget.invoiceId}
                        studentName={reminderTarget.studentName}
                    />
                ) : null}

                <CrudModal
                    open={importOpen}
                    onClose={() => setImportOpen(false)}
                    title="Import tagihan (Excel)"
                    subtitle="Gunakan sheet Tagihan dari template. Proses berjalan di background."
                >
                    <form onSubmit={handleImportSubmit}>
                        <div className="mcr-form-grid">
                            <div className="mcr-form-group full">
                                <label htmlFor="invoice-import-file">File</label>
                                <input
                                    id="invoice-import-file"
                                    className="mcr-input"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                                />
                                <InputError message={importForm.errors.file} />
                            </div>
                            <div className="mcr-form-group full">
                                <label htmlFor="invoice-import-strategy">Strategi duplikat</label>
                                <select
                                    id="invoice-import-strategy"
                                    className="mcr-form-select"
                                    value={importForm.data.strategy}
                                    onChange={(e) => importForm.setData('strategy', e.target.value as 'skip' | 'update')}
                                >
                                    <option value="skip">Lewati baris jika tagihan sudah ada</option>
                                    <option value="update">Sama seperti lewati (v1 tidak menimpa tagihan)</option>
                                </select>
                                <InputError message={importForm.errors.strategy} />
                            </div>
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <button type="button" className="mcr-btn secondary" onClick={() => openDownload('/admin/invoices-template?format=xlsx')}>
                                <Download size={14} />
                                Unduh template
                            </button>
                            <button type="button" className="mcr-btn ghost" onClick={() => setImportOpen(false)}>
                                Batal
                            </button>
                            <button type="submit" className="mcr-btn primary" disabled={importForm.processing}>
                                {importForm.processing ? 'Mengunggah…' : 'Kirim ke antrean'}
                            </button>
                        </div>
                    </form>
                </CrudModal>
            </div>
        </AppLayout>
    );
}
