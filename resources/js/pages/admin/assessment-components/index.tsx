import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Percent, Plus, Scale, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import type { AssessmentComponent, BreadcrumbItem } from '@/types';
import { toast } from 'sonner';

type Props = {
    components: AssessmentComponent[];
};

type ComponentForm = {
    name: string;
    type: string;
    weight: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Komponen Penilaian', href: '/admin/assessment-components' },
];

const typeLabels: Record<string, string> = {
    daily: 'Harian',
    exam: 'Ujian',
};

export default function AssessmentComponentIndex({ components }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<AssessmentComponent | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<AssessmentComponent | null>(null);

    const form = useForm<ComponentForm>({ name: '', type: 'daily', weight: '' });

    const dailyCount = useMemo(() => components.filter((item) => item.type === 'daily').length, [components]);
    const examCount = useMemo(() => components.filter((item) => item.type === 'exam').length, [components]);

    function openCreate() {
        setEditing(null);
        form.setData({ name: '', type: 'daily', weight: '' });
        form.clearErrors();
        setModalOpen(true);
    }

    function openEdit(item: AssessmentComponent) {
        setEditing(item);
        form.setData({
            name: item.name,
            type: item.type,
            weight: item.weight === null || item.weight === undefined ? '' : String(item.weight),
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            form.put(`/admin/assessment-components/${editing.id}`, {
                onSuccess: () => {
                    setModalOpen(false);
                    setEditing(null);
                    toast.success('Komponen diperbarui');
                },
                onError: () => toast.error('Gagal memperbarui komponen'),
            });
            return;
        }
        form.post('/admin/assessment-components', {
            onSuccess: () => {
                setModalOpen(false);
                toast.success('Komponen ditambahkan');
            },
            onError: () => toast.error('Gagal menambah komponen'),
        });
    }

    function remove() {
        if (!deleteTarget) return;
        router.delete(`/admin/assessment-components/${deleteTarget.id}`, {
            onSuccess: () => {
                setDeleteTarget(null);
                toast.success('Komponen dihapus');
            },
            onError: () => toast.error('Gagal menghapus komponen'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Komponen Penilaian" />
            <div>
                <CrudPageHeader title="Komponen Penilaian" description="Kelola komponen nilai harian dan ujian." />
                <CrudStatStrip
                    items={[
                        { key: 'all', label: 'Total Komponen', value: components.length, icon: <Scale size={18} />, tone: 'blue' },
                        { key: 'daily', label: 'Harian', value: dailyCount, icon: <Percent size={18} />, tone: 'green' },
                        { key: 'exam', label: 'Ujian', value: examCount, icon: <Scale size={18} />, tone: 'amber' },
                        { key: 'weighted', label: 'Punya Bobot', value: components.filter((x) => x.weight !== null && x.weight !== undefined).length, icon: <Percent size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />
                <CrudToolbar
                    left={<span className="mcr-table-meta">Nama komponen harus unik di dalam tipe yang sama.</span>}
                    right={
                        <button type="button" className="mcr-btn primary" onClick={openCreate}>
                            <Plus size={14} />
                            Tambah Komponen
                        </button>
                    }
                />

                <CrudCard>
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Tipe</th>
                                    <th>Bobot</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {components.length === 0 ? (
                                    <tr><td colSpan={4}><CrudEmptyState title="Belum ada komponen" description="Tambahkan komponen pertama untuk penilaian." /></td></tr>
                                ) : (
                                    components.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.name}</td>
                                            <td><span className="mcr-dot-badge alumni">{typeLabels[item.type] ?? item.type}</span></td>
                                            <td>{item.weight ?? '-'}</td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <button type="button" className="mcr-icon-action" onClick={() => openEdit(item)} title="Edit">
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteTarget(item)} title="Hapus">
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

            <CrudModal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit Komponen' : 'Tambah Komponen'} subtitle="Atur nama, tipe, dan bobot komponen.">
                <form onSubmit={submit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="component-name">Nama Komponen</label>
                            <input id="component-name" className="mcr-input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="component-type">Tipe</label>
                            <select id="component-type" className="mcr-form-select" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                <option value="daily">Harian</option>
                                <option value="exam">Ujian</option>
                            </select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="component-weight">Bobot (Opsional)</label>
                            <input id="component-weight" className="mcr-input" type="number" step="0.01" min={0} value={form.data.weight} onChange={(e) => form.setData('weight', e.target.value)} />
                            <InputError message={form.errors.weight} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setModalOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={remove}
                title="Konfirmasi Hapus Komponen"
                description={`Hapus komponen "${deleteTarget?.name ?? '-'}"?`}
                confirmLabel="Hapus Komponen"
            />
        </AppLayout>
    );
}
