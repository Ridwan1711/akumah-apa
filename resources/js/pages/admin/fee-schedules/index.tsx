import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, BreadcrumbItem, FeeSchedule, PaginatedData, PaymentType } from '@/types';
import { toast } from 'sonner';

type FeeRow = FeeSchedule & {
    payment_type?: Pick<PaymentType, 'id' | 'name' | 'code' | 'category'>;
    academic_year?: Pick<AcademicYear, 'id' | 'name'>;
};

type Props = {
    feeSchedules: PaginatedData<FeeRow>;
    paymentTypes: (Pick<PaymentType, 'id' | 'name' | 'code' | 'category'> & { default_amount: number })[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    classLevels: string[];
    filters: { academic_year_id?: string; payment_type_id?: string; per_page?: string };
    perPageOptions: number[];
};

type CreateForm = {
    payment_type_id: string;
    academic_year_id: string;
    class_level: string;
    amount: string;
    notes: string;
};

type EditForm = {
    amount: string;
    notes: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tarif', href: '/admin/fee-schedules' },
];

function toCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
}

export default function FeeSchedulesIndex({
    feeSchedules,
    paymentTypes,
    academicYears,
    classLevels,
    filters,
    perPageOptions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<FeeRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<FeeRow | null>(null);

    const createForm = useForm<CreateForm>({
        payment_type_id: '',
        academic_year_id: '',
        class_level: '',
        amount: '',
        notes: '',
    });
    const editForm = useForm<EditForm>({ amount: '', notes: '' });

    const filteredCount = feeSchedules.data.length;
    const globalAverage =
        filteredCount > 0
            ? Math.round(feeSchedules.data.reduce((sum, row) => sum + Number(row.amount), 0) / filteredCount)
            : 0;

    function applyFilter(next: Partial<Props['filters']>) {
        router.get('/admin/fee-schedules', { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    }

    function openEdit(item: FeeRow) {
        setEditTarget(item);
        editForm.setData({
            amount: String(item.amount),
            notes: item.notes ?? '',
        });
        editForm.clearErrors();
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/fee-schedules', {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
                toast.success('Tarif ditambahkan');
            },
            onError: () => toast.error('Gagal menambah tarif'),
        });
    }

    function submitEdit(e: React.FormEvent) {
        if (!editTarget) return;
        e.preventDefault();
        editForm.put(`/admin/fee-schedules/${editTarget.id}`, {
            onSuccess: () => {
                setEditTarget(null);
                toast.success('Tarif diperbarui');
            },
            onError: () => toast.error('Gagal memperbarui tarif'),
        });
    }

    function deleteItem() {
        if (!deleteTarget) return;
        router.delete(`/admin/fee-schedules/${deleteTarget.id}`, {
            onSuccess: () => {
                setDeleteTarget(null);
                toast.success('Tarif dihapus');
            },
            onError: () => toast.error('Gagal menghapus tarif'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tarif" />
            <div>
                <CrudPageHeader
                    title="Tarif Pembayaran"
                    description="Kelola tarif berdasarkan jenis pembayaran, tahun ajaran, dan level kelas."
                />
                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Tarif', value: feeSchedules.total, icon: <Wallet size={18} />, tone: 'blue' },
                        { key: 'types', label: 'Jenis Pembayaran', value: paymentTypes.length, icon: <Wallet size={18} />, tone: 'green' },
                        { key: 'years', label: 'Tahun Ajaran', value: academicYears.length, icon: <Wallet size={18} />, tone: 'amber' },
                        { key: 'avg', label: 'Rata-rata Halaman', value: toCurrency(globalAverage), icon: <Wallet size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select className="mcr-filter-select" value={filters.academic_year_id ?? 'all'} onChange={(e) => applyFilter({ academic_year_id: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Tahun Ajaran</option>
                                {academicYears.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.payment_type_id ?? 'all'} onChange={(e) => applyFilter({ payment_type_id: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Jenis Bayar</option>
                                {paymentTypes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.per_page ?? String(perPageOptions[0] ?? 25)} onChange={(e) => applyFilter({ per_page: e.target.value })}>
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>{opt} / halaman</option>
                                ))}
                            </select>
                        </>
                    }
                    right={
                        <button type="button" className="mcr-btn primary" onClick={() => setCreateOpen(true)}>
                            <Plus size={14} />
                            Tambah Tarif
                        </button>
                    }
                />

                <CrudCard>
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Jenis Pembayaran</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Level</th>
                                    <th>Jumlah</th>
                                    <th>Catatan</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {feeSchedules.data.length === 0 ? (
                                    <tr><td colSpan={6}><CrudEmptyState title="Tidak ada data tarif" description="Tambahkan tarif baru atau ubah filter." /></td></tr>
                                ) : (
                                    feeSchedules.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.payment_type?.name ?? '-'}</td>
                                            <td>{item.academic_year?.name ?? '-'}</td>
                                            <td>{item.class_level ?? 'Semua Level'}</td>
                                            <td>{toCurrency(Number(item.amount))}</td>
                                            <td>{item.notes ?? '-'}</td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <button type="button" className="mcr-icon-action" onClick={() => openEdit(item)} title="Edit tarif">
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteTarget(item)} title="Hapus tarif">
                                                        <Trash2 size={13} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={feeSchedules.links} />
                </CrudCard>
            </div>

            <CrudModal open={createOpen} onClose={() => setCreateOpen(false)} title="Tambah Tarif" subtitle="Tarif unik berdasarkan jenis bayar + tahun ajaran + level.">
                <form onSubmit={submitCreate}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="create-payment-type">Jenis Pembayaran</label>
                            <select id="create-payment-type" className="mcr-form-select" value={createForm.data.payment_type_id} onChange={(e) => createForm.setData('payment_type_id', e.target.value)}>
                                <option value="">Pilih jenis pembayaran</option>
                                {paymentTypes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name} ({item.code})</option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.payment_type_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="create-academic-year">Tahun Ajaran</label>
                            <select id="create-academic-year" className="mcr-form-select" value={createForm.data.academic_year_id} onChange={(e) => createForm.setData('academic_year_id', e.target.value)}>
                                <option value="">Pilih tahun ajaran</option>
                                {academicYears.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.academic_year_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="create-class-level">Level Kelas</label>
                            <select id="create-class-level" className="mcr-form-select" value={createForm.data.class_level} onChange={(e) => createForm.setData('class_level', e.target.value)}>
                                <option value="">Semua Level</option>
                                {classLevels.map((item) => (
                                    <option key={item} value={item}>{item}</option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.class_level} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="create-amount">Nominal</label>
                            <input id="create-amount" className="mcr-input" type="number" min={0} value={createForm.data.amount} onChange={(e) => createForm.setData('amount', e.target.value)} />
                            <InputError message={createForm.errors.amount} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="create-notes">Catatan</label>
                            <textarea id="create-notes" className="mcr-textarea" value={createForm.data.notes} onChange={(e) => createForm.setData('notes', e.target.value)} />
                            <InputError message={createForm.errors.notes} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setCreateOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={createForm.processing}>{createForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal open={editTarget !== null} onClose={() => setEditTarget(null)} title="Edit Tarif" subtitle="Perbarui nominal dan catatan tarif.">
                <form onSubmit={submitEdit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="edit-amount">Nominal</label>
                            <input id="edit-amount" className="mcr-input" type="number" min={0} value={editForm.data.amount} onChange={(e) => editForm.setData('amount', e.target.value)} />
                            <InputError message={editForm.errors.amount} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="edit-notes">Catatan</label>
                            <textarea id="edit-notes" className="mcr-textarea" value={editForm.data.notes} onChange={(e) => editForm.setData('notes', e.target.value)} />
                            <InputError message={editForm.errors.notes} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setEditTarget(null)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={editForm.processing}>{editForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={deleteItem}
                title="Konfirmasi Hapus Tarif"
                description={`Hapus tarif "${deleteTarget?.payment_type?.name ?? '-'}"?`}
                confirmLabel="Hapus Tarif"
            />
        </AppLayout>
    );
}
