import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, BreadcrumbItem, TingkatSekolahFormal } from '@/types';
import { memberListUrl } from '../students/member-list-url';

type Row = TingkatSekolahFormal & { enrollments_count?: number };

type Props = {
    tingkatSekolahs: Row[];
    knownCodes: string[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    selectedAcademicYearId: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Master Tingkat Formal', href: '/admin/tingkat-sekolahs' },
];

type FormShape = {
    name: string;
    code: string;
    group: string;
    order: string;
    is_billable: boolean;
};

const emptyForm: FormShape = {
    name: '',
    code: '',
    group: '',
    order: '0',
    is_billable: true,
};

export default function TingkatSekolahIndex({
    tingkatSekolahs,
    knownCodes,
    academicYears,
    selectedAcademicYearId,
}: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<Row | null>(null);
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);

    const form = useForm<FormShape>(emptyForm);

    const codesHint = useMemo(() => knownCodes.join(', '), [knownCodes]);

    function openCreate() {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(row: Row) {
        setEditing(row);
        form.setData({
            name: row.name,
            code: row.code ?? '',
            group: row.group ?? '',
            order: String(row.order ?? 0),
            is_billable: row.is_billable ?? true,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            form.put(`/admin/tingkat-sekolahs/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    setEditing(null);
                    form.reset();
                    toast.success('Tingkat diperbarui');
                },
                onError: () => toast.error('Gagal memperbarui tingkat'),
            });
            return;
        }
        form.post('/admin/tingkat-sekolahs', {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset();
                toast.success('Tingkat ditambahkan');
            },
            onError: () => toast.error('Gagal menambah tingkat'),
        });
    }

    function confirmDelete(row: Row) {
        setToDelete(row);
        setDeleteOpen(true);
    }

    function runDelete() {
        if (!toDelete) return;
        setDeleteBusy(true);
        router.delete(`/admin/tingkat-sekolahs/${toDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Tingkat dihapus');
                setDeleteOpen(false);
                setToDelete(null);
            },
            onError: () => toast.error('Gagal menghapus tingkat'),
            onFinish: () => setDeleteBusy(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Tingkat Formal" />
            <div>
                <CrudPageHeader
                    title="Master tingkat sekolah formal"
                    description="MTs, MA, kuliah, dll. Dipakai enrollment formal, impor Excel, dan aturan tarif invoice. Bukan kelas diniyyah."
                />
                <CrudStatStrip
                    items={[
                        { key: 'n', label: 'Jumlah tingkat', value: tingkatSekolahs.length, icon: <Layers size={18} />, tone: 'blue' },
                    ]}
                />
                <FlashMessage />
                <CrudToolbar
                    left={
                        <span className="mcr-table-meta">
                            Jumlah santri per tingkat mengikuti enrollment tahun ajaran terpilih.
                        </span>
                    }
                    right={
                        <>
                            <select
                                className="mcr-filter-select"
                                value={String(selectedAcademicYearId)}
                                onChange={(e) =>
                                    router.get('/admin/tingkat-sekolahs', { academic_year_id: e.target.value }, {
                                        preserveState: true,
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {academicYears.map((year) => (
                                    <option key={year.id} value={String(year.id)}>
                                        TA {year.name}
                                    </option>
                                ))}
                            </select>
                        <button type="button" className="mcr-btn primary" onClick={openCreate}>
                            <Plus size={14} />
                            Tambah tingkat
                        </button>
                        </>
                    }
                />
                <CrudCard title="Daftar tingkat">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Urutan</th>
                                    <th>Nama</th>
                                    <th>Kode</th>
                                    <th>Grup</th>
                                    <th>Tagihan</th>
                                    <th>Santri</th>
                                    <th style={{ width: 140 }} />
                                </tr>
                            </thead>
                            <tbody>
                                {tingkatSekolahs.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <CrudEmptyState title="Belum ada data" description="Tambahkan tingkat untuk enrollment dan keuangan." />
                                        </td>
                                    </tr>
                                ) : (
                                    tingkatSekolahs.map((row) => (
                                        <tr key={row.id}>
                                            <td>{row.order ?? 0}</td>
                                            <td>{row.name}</td>
                                            <td>
                                                <code>{row.code ?? '—'}</code>
                                            </td>
                                            <td>{row.group ?? '—'}</td>
                                            <td>{row.is_billable ? 'Ya' : 'Tidak'}</td>
                                            <td>
                                                <span className="mcr-dot-badge active">{row.enrollments_count ?? 0}</span>
                                            </td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <Link
                                                        href={memberListUrl({
                                                            tingkat_sekolah_id: row.id,
                                                            academic_year_id: selectedAcademicYearId,
                                                        })}
                                                        className="mcr-icon-action"
                                                        title="Lihat anggota tingkat formal"
                                                    >
                                                        <Eye size={13} />
                                                    </Link>
                                                    <button type="button" className="mcr-icon-action" title="Edit" onClick={() => openEdit(row)}>
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button type="button" className="mcr-icon-action danger" title="Hapus" onClick={() => confirmDelete(row)}>
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
                </CrudCard>
            </div>

            <CrudModal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit tingkat' : 'Tambah tingkat'}>
                <form onSubmit={submit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="ts-name">Nama</label>
                            <input id="ts-name" className="mcr-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="ts-code">Kode (unik)</label>
                            <input id="ts-code" className="mcr-input" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="mis. mts_7" />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="ts-group">Grup</label>
                            <input id="ts-group" className="mcr-input" value={form.data.group} onChange={(e) => form.setData('group', e.target.value)} placeholder="MTs / MA / Kuliah" />
                            <InputError message={form.errors.group} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="ts-order">Urutan</label>
                            <input
                                id="ts-order"
                                type="number"
                                min={0}
                                className="mcr-input"
                                value={form.data.order}
                                onChange={(e) => form.setData('order', e.target.value)}
                            />
                            <InputError message={form.errors.order} />
                        </div>
                        <label className="mcr-form-group">
                            <span>Diperhitungkan untuk tagihan</span>
                            <input type="checkbox" checked={form.data.is_billable} onChange={(e) => form.setData('is_billable', e.target.checked)} />
                            <InputError message={form.errors.is_billable} />
                        </label>
                    </div>
                    {codesHint ? (
                        <p className="mcr-table-meta" style={{ marginTop: 8 }}>
                            Kode bawaan template impor: <code>{codesHint}</code>
                        </p>
                    ) : null}
                    <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setModalOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteOpen}
                onClose={() => {
                    setDeleteOpen(false);
                    setToDelete(null);
                }}
                title="Hapus tingkat?"
                description={toDelete ? `Hapus tingkat "${toDelete.name}"?` : ''}
                warningText="Hanya bisa dihapus jika tidak ada enrollment, aturan jenis tagihan, atau tagihan yang mereferensinya."
                confirmLabel="Hapus"
                onConfirm={runDelete}
                loading={deleteBusy}
            />
        </AppLayout>
    );
}
