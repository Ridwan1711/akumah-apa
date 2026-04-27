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
import { can } from '@/lib/authz';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, Auth, BreadcrumbItem, Invoice, PaginatedData, PaymentType, SchoolClass } from '@/types';

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
    invoices: PaginatedData<Invoice>;
    paymentTypes: Pick<PaymentType, 'id' | 'name' | 'code'>[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    classes: Pick<SchoolClass, 'id' | 'name'>[];
    filters: Record<string, string | undefined>;
    statusCounts: Record<string, number>;
};

export default function InvoiceIndex({ invoices, paymentTypes, academicYears, classes, filters, statusCounts }: Props) {
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
                        { key: 'all', label: 'Total Tagihan', value: statusCounts.all ?? invoices.total, icon: <Wallet size={18} />, tone: 'blue' },
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
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>No. Invoice</th>
                                    <th>Santri</th>
                                    <th>Jenis Bayar</th>
                                    <th>Periode</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <CrudEmptyState title="Tidak ada tagihan" description="Belum ada invoice sesuai filter saat ini." />
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.data.map((invoice) => (
                                        <tr key={invoice.id}>
                                            <td>{invoice.invoice_number}</td>
                                            <td>{invoice.student?.full_name ?? '-'} ({invoice.student?.nis ?? '-'})</td>
                                            <td>{invoice.payment_type?.name ?? '-'} ({invoice.payment_type?.code ?? '-'})</td>
                                            <td>{invoice.academic_year?.name ?? '-'}{invoice.month ? ` • ${monthNames[invoice.month]}` : ''}</td>
                                            <td>{formatCurrency(Number(invoice.final_amount))}</td>
                                            <td>
                                                <span className={`mcr-dot-badge ${invoice.status === 'paid' ? 'active' : invoice.status === 'overdue' ? 'wafat' : 'alumni'}`}>
                                                    {statusLabels[invoice.status] ?? invoice.status}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <Link href={`/admin/invoices/${invoice.id}`} className="mcr-icon-action" title="Detail">
                                                        <Eye size={13} />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={invoices.links} />
                </CrudCard>
            </div>
        </AppLayout>
    );
}
