import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
    const breakdownForm = useForm<{ breakdown: Array<{ label: string; amount: string }> }>({
        breakdown: (invoice.breakdown_items ?? invoice.breakdown ?? []).map((item) => ({
            label: item.label,
            amount: String(item.amount),
        })),
    });

    function handleCancel() {
        router.post(`/admin/invoices/${invoice.id}/cancel`, undefined, {
            onSuccess: () => {
                setCancelOpen(false);
                toast.success('Tagihan dibatalkan');
            },
            onError: () => toast.error('Gagal membatalkan tagihan'),
        });
    }

    function appendBreakdownRow() {
        breakdownForm.setData('breakdown', [...breakdownForm.data.breakdown, { label: '', amount: '' }]);
    }

    function removeBreakdownRow(index: number) {
        breakdownForm.setData(
            'breakdown',
            breakdownForm.data.breakdown.filter((_, i) => i !== index),
        );
    }

    function setBreakdownField(index: number, field: 'label' | 'amount', value: string) {
        breakdownForm.setData(
            'breakdown',
            breakdownForm.data.breakdown.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
        );
    }

    function saveBreakdown() {
        breakdownForm.transform((data) => ({
            breakdown: data.breakdown
                .filter((item) => item.label.trim() !== '' && item.amount !== '')
                .map((item) => ({
                    label: item.label.trim(),
                    amount: Number(item.amount),
                })),
        }));

        breakdownForm.put(`/admin/invoices/${invoice.id}/breakdown`, {
            preserveScroll: true,
            onFinish: () => breakdownForm.transform((data) => data),
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
                        { key: 'pending', label: 'Pending Verifikasi', value: formatCurrency(Number(invoice.pending_amount ?? 0)), icon: <Ban size={18} />, tone: 'amber' },
                        { key: 'remain', label: 'Sisa', value: formatCurrency(Number(invoice.remaining ?? 0)), icon: <Ban size={18} />, tone: 'purple' },
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
                        <div className="mcr-action-group">
                            {invoice.status !== 'paid' && invoice.status !== 'cancelled' ? (
                                <Link href={`/admin/payments/create?invoice_id=${invoice.id}`} className="mcr-btn secondary">
                                    Catat Pembayaran Manual
                                </Link>
                            ) : null}
                            {invoice.status !== 'paid' && invoice.status !== 'cancelled' ? (
                                <button type="button" className="mcr-btn danger" onClick={() => setCancelOpen(true)}>
                                    <Ban size={14} />
                                    Batalkan Tagihan
                                </button>
                            ) : null}
                        </div>
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

                <CrudCard
                    title="Rincian Tagihan"
                    subtitle="Komponen biaya per jenis tagihan. Total rincian wajib sama dengan nominal tagihan."
                    right={(
                        <button type="button" className="mcr-btn ghost" onClick={appendBreakdownRow}>
                            <Plus size={14} />
                            Tambah Item
                        </button>
                    )}
                >
                    {breakdownForm.data.breakdown.length === 0 ? (
                        <p className="mcr-table-meta">Belum ada rincian. Klik "Tambah Item" untuk mengisi rincian tagihan.</p>
                    ) : (
                        <div style={{ display: 'grid', gap: 8 }}>
                            {breakdownForm.data.breakdown.map((item, index) => (
                                <div key={`invoice-breakdown-${index}`} style={{ display: 'grid', gridTemplateColumns: '1fr 180px auto', gap: 8 }}>
                                    <input
                                        className="mcr-input"
                                        value={item.label}
                                        onChange={(e) => setBreakdownField(index, 'label', e.target.value)}
                                        placeholder="Nama rincian"
                                    />
                                    <input
                                        className="mcr-input"
                                        type="number"
                                        min={0}
                                        value={item.amount}
                                        onChange={(e) => setBreakdownField(index, 'amount', e.target.value)}
                                        placeholder="Nominal"
                                    />
                                    <button type="button" className="mcr-btn ghost" onClick={() => removeBreakdownRow(index)}>
                                        <Trash2 size={14} />
                                        Hapus
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                    <p className="mcr-table-meta" style={{ marginTop: 10 }}>
                        Total rincian: {formatCurrency(breakdownForm.data.breakdown.reduce((sum, item) => sum + (Number(item.amount) || 0), 0))} •
                        Nominal tagihan: {formatCurrency(Number(invoice.amount))}
                    </p>
                    {breakdownForm.errors.breakdown ? <p className="mcr-table-meta" style={{ color: '#dc2626' }}>{breakdownForm.errors.breakdown}</p> : null}
                    <div style={{ marginTop: 10 }}>
                        <button type="button" className="mcr-btn primary" onClick={saveBreakdown} disabled={breakdownForm.processing}>
                            <Save size={14} />
                            {breakdownForm.processing ? 'Menyimpan...' : 'Simpan Rincian'}
                        </button>
                    </div>
                </CrudCard>

                <CrudCard title="Timeline Pembayaran">
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
