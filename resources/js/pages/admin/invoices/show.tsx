import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Ban } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudConfirmModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Invoice } from '@/types';
import { useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/admin/invoices' },
    { title: 'Detail', href: '#' },
];

const statusLabels: Record<string, string> = {
    pending: 'Belum Bayar',
    paid: 'Lunas',
    partial: 'Sebagian',
    overdue: 'Jatuh Tempo',
    cancelled: 'Dibatalkan',
};

const paymentStatusLabels: Record<string, string> = {
    pending: 'Menunggu',
    verified: 'Terverifikasi',
    rejected: 'Ditolak',
};

const paymentMethodLabels: Record<string, string> = {
    cash: 'Tunai',
    bank_transfer: 'Transfer Bank',
    gateway: 'Payment Gateway',
};

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = { invoice: Invoice };

export default function InvoiceShow({ invoice }: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);

    function handleCancel() {
        router.post(`/admin/invoices/${invoice.id}/cancel`, undefined, {
            onSuccess: () => {
                setCancelOpen(false);
                toast.success('Tagihan dibatalkan');
            },
            onError: () => toast.error('Gagal membatalkan tagihan'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Detail Invoice ${invoice.invoice_number}`} />
            <div>
                <CrudPageHeader
                    title={`Invoice ${invoice.invoice_number}`}
                    description="Detail tagihan, status, dan riwayat pembayaran."
                />

                <CrudStatStrip
                    items={[
                        { key: 'amount', label: 'Total Tagihan', value: formatCurrency(Number(invoice.final_amount)), icon: <Ban size={18} />, tone: 'blue' },
                        { key: 'paid', label: 'Sudah Dibayar', value: formatCurrency(Number(invoice.total_paid ?? 0)), icon: <Ban size={18} />, tone: 'green' },
                        { key: 'remain', label: 'Sisa', value: formatCurrency(Number(invoice.remaining ?? 0)), icon: <Ban size={18} />, tone: 'amber' },
                        { key: 'status', label: 'Status', value: statusLabels[invoice.status] ?? invoice.status, icon: <Ban size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />
                <CrudToolbar
                    left={
                        <Link href="/admin/invoices" className="mcr-btn ghost">
                            <ArrowLeft size={14} />
                            Kembali ke daftar
                        </Link>
                    }
                    right={
                        invoice.status !== 'paid' && invoice.status !== 'cancelled' ? (
                            <button type="button" className="mcr-btn danger" onClick={() => setCancelOpen(true)}>
                                <Ban size={14} />
                                Batalkan Tagihan
                            </button>
                        ) : undefined
                    }
                />

                <CrudCard title="Informasi Tagihan">
                    <div className="mcr-run-stats">
                        <span>Santri: {invoice.student?.full_name ?? '-'}</span>
                        <span>NIS: {invoice.student?.nis ?? '-'}</span>
                        <span>Kelas: {invoice.student?.current_class?.name ?? '-'}</span>
                        <span>Jenis Bayar: {invoice.payment_type?.name ?? '-'} ({invoice.payment_type?.code ?? '-'})</span>
                        <span>Tahun Ajaran: {invoice.academic_year?.name ?? '-'}</span>
                        <span>Bulan: {invoice.month ? monthNames[invoice.month] : '-'}</span>
                        <span>Jatuh Tempo: {invoice.due_date}</span>
                        <span>Status: {statusLabels[invoice.status] ?? invoice.status}</span>
                    </div>
                </CrudCard>

                <CrudCard title="Riwayat Pembayaran">
                    {!invoice.payments || invoice.payments.length === 0 ? (
                        <p className="mcr-table-meta">Belum ada pembayaran untuk invoice ini.</p>
                    ) : (
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>No. Pembayaran</th>
                                    <th>Tanggal</th>
                                    <th>Metode</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Verifier</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoice.payments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td>{payment.payment_number}</td>
                                        <td>{payment.payment_date}</td>
                                        <td>{paymentMethodLabels[payment.payment_method] ?? payment.payment_method}</td>
                                        <td>{formatCurrency(Number(payment.amount))}</td>
                                        <td>
                                            <span className="mcr-dot-badge alumni">
                                                {paymentStatusLabels[payment.status] ?? payment.status}
                                            </span>
                                        </td>
                                        <td>{payment.verifier?.name ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </CrudCard>
            </div>

            <CrudConfirmModal
                open={cancelOpen}
                onClose={() => setCancelOpen(false)}
                onConfirm={handleCancel}
                title="Konfirmasi Pembatalan"
                description={`Batalkan invoice ${invoice.invoice_number}?`}
                confirmLabel="Batalkan Invoice"
            />
        </AppLayout>
    );
}
