import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, FilePlus2, Search, Wallet } from 'lucide-react';
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
import { can } from '@/lib/authz';
import type {
    AcademicYear,
    Auth,
    BreadcrumbItem,
    PaginatedData,
    PaymentType,
    SchoolClass,
    StudentInvoiceGroup,
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

type Props = {
    studentGroups: PaginatedData<StudentInvoiceGroup>;
    totalInvoiceCount: number;
    paymentTypes: Pick<PaymentType, 'id' | 'name' | 'code'>[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    classes: Pick<SchoolClass, 'id' | 'name'>[];
    divisionOptions: string[];
    filters: Record<string, string | undefined>;
    statusCounts: Record<string, number>;
};

export default function InvoiceIndex({
    studentGroups,
    totalInvoiceCount,
    paymentTypes,
    academicYears,
    classes,
    divisionOptions,
    filters,
    statusCounts,
}: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canCreateInvoice = can(auth, 'invoice.create');

    function handleFilter(key: string, value: string) {
        router.get(
            '/admin/invoices',
            { ...filters, [key]: value || undefined, page: undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tagihan" />
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
                            <select className="mcr-filter-select" value={filters.class_id ?? ''} onChange={(e) => handleFilter('class_id', e.target.value)}>
                                <option value="">Semua Kelas</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
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
                        canCreateInvoice ? (
                        <Link href="/admin/invoices/generate" className="mcr-btn primary">
                            <FilePlus2 size={14} />
                            Bulk Generate
                        </Link>
                        ) : null
                    }
                />

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
                                        <div>
                                            <div className="font-medium">{group.student_name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                NIS: {group.student_nis}
                                                {group.class_name ? ` • ${group.class_name}` : ''}
                                            </div>
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
            </div>
        </AppLayout>
    );
}
