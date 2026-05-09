import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, CreditCard, Plus, Settings2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudConfirmModal,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import type { Auth, BreadcrumbItem, PaginatedData, Payment, PaymentType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pembayaran', href: '/admin/payments' },
];

const statusLabels: Record<string, string> = { pending: 'Menunggu', verified: 'Terverifikasi', rejected: 'Ditolak' };
const methodLabels: Record<string, string> = { cash: 'Tunai', bank_transfer: 'Transfer Bank', gateway: 'Payment Gateway' };

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    payments: PaginatedData<Payment>;
    filters: Record<string, string | undefined>;
    pendingCount: number;
    paymentTypes: PaymentType[];
};

export default function PaymentIndex({ payments, filters, pendingCount, paymentTypes }: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canManagePaymentTypes = can(auth, 'invoice.create');
    const canVerifyPayment = can(auth, 'payment.verify');
    const canRejectPayment = can(auth, 'payment.reject');

    const [search, setSearch] = useState(filters.search ?? '');
    const [typeModalOpen, setTypeModalOpen] = useState(false);
    const [editingType, setEditingType] = useState<PaymentType | null>(null);
    const [deleteType, setDeleteType] = useState<PaymentType | null>(null);
    const [verifyTarget, setVerifyTarget] = useState<Payment | null>(null);
    const [rejectTarget, setRejectTarget] = useState<Payment | null>(null);

    const typeForm = useForm({
        name: '',
        code: '',
        category: 'spp',
        is_recurring: true,
        default_amount: 0,
        description: '',
        is_active: true,
    });

    const pendingDisplay = useMemo(() => pendingCount, [pendingCount]);

    function handleFilter(key: string, value: string) {
        router.get('/admin/payments', { ...filters, [key]: value || undefined, page: undefined }, { preserveState: true });
    }

    function openTypeCreate() {
        setEditingType(null);
        typeForm.setData({
            name: '',
            code: '',
            category: 'spp',
            is_recurring: true,
            default_amount: 0,
            description: '',
            is_active: true,
        });
        setTypeModalOpen(true);
    }

    function openTypeEdit(item: PaymentType) {
        setEditingType(item);
        typeForm.setData({
            name: item.name,
            code: item.code,
            category: item.category,
            is_recurring: item.is_recurring,
            default_amount: item.default_amount,
            description: item.description ?? '',
            is_active: item.is_active,
        });
        setTypeModalOpen(true);
    }

    function submitTypeForm(e: React.FormEvent) {
        e.preventDefault();
        if (editingType) {
            typeForm.put(`/admin/payment-types/${editingType.id}`, {
                preserveScroll: true,
                onSuccess: () => setTypeModalOpen(false),
            });
            return;
        }

        typeForm.post('/admin/payment-types', {
            preserveScroll: true,
            onSuccess: () => setTypeModalOpen(false),
        });
    }

    function verifyPayment() {
        if (!verifyTarget) return;
        router.post(`/admin/payments/${verifyTarget.id}/verify`, {}, {
            preserveScroll: true,
            onFinish: () => setVerifyTarget(null),
        });
    }

    function rejectPayment() {
        if (!rejectTarget) return;
        router.post(`/admin/payments/${rejectTarget.id}/reject`, {}, {
            preserveScroll: true,
            onFinish: () => setRejectTarget(null),
        });
    }

    function destroyType() {
        if (!deleteType) return;
        router.delete(`/admin/payment-types/${deleteType.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleteType(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pembayaran" />
            <div>
                <CrudPageHeader
                    title="Pembayaran & Jenis Pembayaran"
                    description="Verifikasi pembayaran santri sekaligus kelola master jenis pembayaran di satu halaman."
                />

                <CrudStatStrip
                    items={[
                        { key: 'all', label: 'Total Pembayaran', value: payments.total, icon: <CreditCard size={18} />, tone: 'blue' },
                        { key: 'pending', label: 'Menunggu Verifikasi', value: pendingDisplay, icon: <CreditCard size={18} />, tone: 'amber' },
                        { key: 'types', label: 'Jenis Pembayaran', value: paymentTypes.length, icon: <Settings2 size={18} />, tone: 'purple' },
                        { key: 'methods', label: 'Metode Aktif', value: 2, icon: <CreditCard size={18} />, tone: 'green' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={(
                        <>
                            <div className="mcr-search">
                                <input
                                    placeholder="Cari nama/NIS..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') handleFilter('search', search);
                                    }}
                                />
                            </div>
                            <select className="mcr-filter-select" value={filters.status ?? ''} onChange={(e) => handleFilter('status', e.target.value)}>
                                <option value="">Semua status</option>
                                <option value="pending">Menunggu</option>
                                <option value="verified">Terverifikasi</option>
                                <option value="rejected">Ditolak</option>
                            </select>
                            <select className="mcr-filter-select" value={filters.payment_method ?? ''} onChange={(e) => handleFilter('payment_method', e.target.value)}>
                                <option value="">Semua metode</option>
                                <option value="cash">Tunai</option>
                                <option value="bank_transfer">Transfer Bank</option>
                                <option value="gateway">Gateway</option>
                            </select>
                            <select className="mcr-filter-select" value={filters.payment_kind ?? ''} onChange={(e) => handleFilter('payment_kind', e.target.value)}>
                                <option value="">Semua jenis bayar</option>
                                <option value="full">Pembayaran penuh</option>
                                <option value="partial">Cicilan / sebagian</option>
                            </select>
                        </>
                    )}
                    right={(
                        <>
                            {canManagePaymentTypes ? (
                                <button type="button" className="mcr-btn secondary" onClick={openTypeCreate}>
                                    <Settings2 size={14} />
                                    Jenis Pembayaran
                                </button>
                            ) : null}
                            {canVerifyPayment ? (
                                <Link href="/admin/payments/create" className="mcr-btn primary">
                                    <Plus size={14} />
                                    Catat Bayar
                                </Link>
                            ) : null}
                        </>
                    )}
                />

                <CrudCard title="Daftar Pembayaran">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>No. Bayar</th>
                                    <th>Santri</th>
                                    <th>Invoice</th>
                                    <th>Metode</th>
                                    <th style={{ textAlign: 'right' }}>Jumlah</th>
                                    <th style={{ textAlign: 'right' }}>Sisa Tagihan</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.data.map((item) => (
                                    <tr key={item.id}>
                                        <td><code>{item.payment_number}</code></td>
                                        <td>{item.invoice?.student?.full_name}</td>
                                        <td>
                                            <Link href={`/admin/invoices/${item.invoice_id}`} className="mcr-table-link">
                                                {item.invoice?.invoice_number}
                                            </Link>
                                        </td>
                                        <td>{methodLabels[item.payment_method]}</td>
                                        <td style={{ textAlign: 'right' }}>{formatCurrency(item.amount)}</td>
                                        <td style={{ textAlign: 'right' }}>{formatCurrency(Number(item.invoice?.remaining ?? item.invoice?.final_amount ?? 0))}</td>
                                        <td>{item.payment_date}</td>
                                        <td>
                                            <span className={`mcr-dot-badge ${
                                                item.status === 'verified'
                                                    ? 'active'
                                                    : item.status === 'rejected'
                                                        ? 'wafat'
                                                        : 'keluar'
                                            }`}
                                            >
                                                {statusLabels[item.status]}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="mcr-action-group">
                                                {item.status === 'pending' && (canVerifyPayment || canRejectPayment) ? (
                                                    <>
                                                        {canVerifyPayment ? (
                                                            <button type="button" className="mcr-btn secondary" onClick={() => setVerifyTarget(item)}>
                                                                <Check size={14} />
                                                                Verifikasi
                                                            </button>
                                                        ) : null}
                                                        {canRejectPayment ? (
                                                            <button type="button" className="mcr-btn danger" onClick={() => setRejectTarget(item)}>
                                                                <X size={14} />
                                                                Tolak
                                                            </button>
                                                        ) : null}
                                                    </>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={payments.links} />
                </CrudCard>

                <CrudCard title="Master Jenis Pembayaran" subtitle="Kelola kategori, nominal default, dan status aktif langsung dari halaman ini.">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Kode</th>
                                    <th>Kategori</th>
                                    <th style={{ textAlign: 'right' }}>Nominal</th>
                                    <th>Recurring</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paymentTypes.map((type) => (
                                    <tr key={type.id}>
                                        <td>{type.name}</td>
                                        <td><code>{type.code}</code></td>
                                        <td>{type.category}</td>
                                        <td style={{ textAlign: 'right' }}>{formatCurrency(type.default_amount)}</td>
                                        <td>{type.is_recurring ? 'Ya' : 'Tidak'}</td>
                                        <td>{type.is_active ? 'Aktif' : 'Nonaktif'}</td>
                                        <td>
                                            {canManagePaymentTypes ? (
                                                <div className="mcr-action-group">
                                                    <button type="button" className="mcr-btn ghost" onClick={() => openTypeEdit(type)}>
                                                        Edit
                                                    </button>
                                                    <button type="button" className="mcr-btn danger" onClick={() => setDeleteType(type)}>
                                                        Hapus
                                                    </button>
                                                </div>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CrudTableShell>
                </CrudCard>
            </div>

            <CrudModal
                open={typeModalOpen}
                onClose={() => setTypeModalOpen(false)}
                title={editingType ? 'Edit Jenis Pembayaran' : 'Tambah Jenis Pembayaran'}
                subtitle="Data ini dipakai pada invoice, laporan, dan pembayaran."
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setTypeModalOpen(false)} disabled={typeForm.processing}>
                            Batal
                        </button>
                        <button type="submit" form="payment-type-form" className="mcr-btn primary" disabled={typeForm.processing}>
                            {typeForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </>
                )}
            >
                <form id="payment-type-form" className="mcr-form-grid" onSubmit={submitTypeForm}>
                    <div className="mcr-form-group">
                        <label>Nama</label>
                        <input className="mcr-input" value={typeForm.data.name} onChange={(e) => typeForm.setData('name', e.target.value)} />
                    </div>
                    <div className="mcr-form-group">
                        <label>Kode</label>
                        <input className="mcr-input" value={typeForm.data.code} onChange={(e) => typeForm.setData('code', e.target.value.toUpperCase())} />
                    </div>
                    <div className="mcr-form-group">
                        <label>Kategori</label>
                        <select className="mcr-form-select" value={typeForm.data.category} onChange={(e) => typeForm.setData('category', e.target.value)}>
                            <option value="spp">SPP</option>
                            <option value="non_spp">Non-SPP</option>
                            <option value="infaq">Infaq</option>
                        </select>
                    </div>
                    <div className="mcr-form-group">
                        <label>Nominal Default</label>
                        <input className="mcr-input" type="number" min={0} value={typeForm.data.default_amount} onChange={(e) => typeForm.setData('default_amount', Number(e.target.value))} />
                    </div>
                    <div className="mcr-form-group full">
                        <label>Deskripsi</label>
                        <textarea className="mcr-textarea" value={typeForm.data.description} onChange={(e) => typeForm.setData('description', e.target.value)} />
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteType !== null}
                onClose={() => setDeleteType(null)}
                onConfirm={destroyType}
                title="Hapus Jenis Pembayaran"
                description={`Yakin hapus jenis pembayaran "${deleteType?.name ?? ''}"?`}
            />

            <CrudConfirmModal
                open={verifyTarget !== null}
                onClose={() => setVerifyTarget(null)}
                onConfirm={verifyPayment}
                title="Verifikasi Pembayaran"
                description={`Verifikasi pembayaran ${verifyTarget?.payment_number ?? ''}?`}
                confirmLabel="Verifikasi"
            />

            <CrudConfirmModal
                open={rejectTarget !== null}
                onClose={() => setRejectTarget(null)}
                onConfirm={rejectPayment}
                title="Tolak Pembayaran"
                description={`Tolak pembayaran ${rejectTarget?.payment_number ?? ''}?`}
                confirmLabel="Tolak"
            />
        </AppLayout>
    );
}
