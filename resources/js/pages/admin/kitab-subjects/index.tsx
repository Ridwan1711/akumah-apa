import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, Download, FileText, FileUp, Plus } from 'lucide-react';
import { useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
    openDownload,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Fan, Subject } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Mata Pelajaran', href: '/admin/kitab-subjects' },
];

type Props = {
    subjects: Array<Pick<Subject, 'id' | 'name' | 'fan_id'> & { fan?: Pick<Fan, 'id' | 'name'> | null }>;
    fans: Pick<Fan, 'id' | 'name'>[];
};

export default function KitabSubjectIndex({ subjects, fans }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<Props['subjects'][number] | null>(null);
    const [deleting, setDeleting] = useState<Props['subjects'][number] | null>(null);

    const form = useForm<{ name: string; fan_id: string }>({ name: '', fan_id: '' });
    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'skip',
    });

    function openCreate() {
        setEditing(null);
        form.setData({ name: '', fan_id: '' });
        setModalOpen(true);
    }

    function openEdit(item: Props['subjects'][number]) {
        setEditing(item);
        form.setData({ name: item.name, fan_id: item.fan_id ? String(item.fan_id) : '' });
        setModalOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            fan_id: data.fan_id === '' ? null : Number(data.fan_id),
        }));
        if (editing) {
            form.put(`/admin/kitab-subjects/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => setModalOpen(false),
                onFinish: () => form.transform((data) => data),
            });
            return;
        }
        form.post('/admin/kitab-subjects', {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
            onFinish: () => form.transform((data) => data),
        });
    }

    function destroy() {
        if (!deleting) return;
        router.delete(`/admin/kitab-subjects/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    }

    function handleImportSubmit(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/kitab-subjects-import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mata Pelajaran" />
            <div>
                <CrudPageHeader
                    title="Mata Pelajaran"
                    description="Master mapel untuk penjadwalan, penugasan guru, dan penilaian."
                />

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Mapel', value: subjects.length, icon: <BookOpen size={18} />, tone: 'blue' },
                        { key: 'fans', label: 'Total Fan', value: fans.length, icon: <BookOpen size={18} />, tone: 'green' },
                        { key: 'schedule', label: 'Untuk Jadwal', value: subjects.length, icon: <BookOpen size={18} />, tone: 'amber' },
                        { key: 'grade', label: 'Untuk Penilaian', value: subjects.length, icon: <BookOpen size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={null}
                    right={(
                        <>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload('/admin/kitab-subjects-template?format=xlsx')}
                            >
                                <FileText size={14} />
                                Template
                            </button>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload('/admin/kitab-subjects-export?format=xlsx')}
                            >
                                <Download size={14} />
                                Export XLSX
                            </button>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => setImportOpen(true)}
                            >
                                <FileUp size={14} />
                                Import
                            </button>
                            <button type="button" className="mcr-btn primary" onClick={openCreate}>
                                <Plus size={14} />
                                Tambah Mapel
                            </button>
                        </>
                    )}
                />

                <CrudCard title="Daftar Mata Pelajaran">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Fan</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((item) => (
                                    <tr key={item.id}>
                                        <td style={{ fontWeight: 600 }}>{item.name}</td>
                                        <td>{item.fan?.name ?? '-'}</td>
                                        <td>
                                            <div className="mcr-action-group">
                                                <button type="button" className="mcr-btn ghost" onClick={() => openEdit(item)}>
                                                    Edit
                                                </button>
                                                <button type="button" className="mcr-btn danger" onClick={() => setDeleting(item)}>
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CrudTableShell>
                </CrudCard>
            </div>

            <CrudModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Mata Pelajaran' : 'Mata Pelajaran Baru'}
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setModalOpen(false)} disabled={form.processing}>
                            Batal
                        </button>
                        <button type="submit" form="subject-form" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </>
                )}
            >
                <form id="subject-form" className="mcr-form-group" onSubmit={submit}>
                    <label>Nama Mata Pelajaran</label>
                    <input
                        className="mcr-input"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Contoh: Nahwu"
                    />
                    <InputError message={form.errors.name} />

                    <label>Fan (Cabang Ilmu)</label>
                    <select
                        className="mcr-input"
                        value={form.data.fan_id}
                        onChange={(e) => form.setData('fan_id', e.target.value)}
                    >
                        <option value="">Tanpa fan</option>
                        {fans.map((fan) => (
                            <option key={fan.id} value={String(fan.id)}>
                                {fan.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.fan_id} />
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                onConfirm={destroy}
                title="Hapus Mata Pelajaran"
                description={`Yakin hapus mapel "${deleting?.name ?? ''}"?`}
            />

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Import Mata Pelajaran"
                subtitle="Unggah file CSV/XLSX dengan kolom name."
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setImportOpen(false)} disabled={importForm.processing}>
                            Batal
                        </button>
                        <button type="submit" form="subject-import-form" className="mcr-btn primary" disabled={importForm.processing}>
                            {importForm.processing ? 'Mengimport...' : 'Import'}
                        </button>
                    </>
                )}
            >
                <form id="subject-import-form" className="mcr-form-group" onSubmit={handleImportSubmit}>
                    <label>File Import</label>
                    <input
                        type="file"
                        className="mcr-input"
                        accept=".xlsx,.csv,.txt"
                        onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                    />
                    <InputError message={importForm.errors.file} />

                    <label>Strategi jika mapel sudah ada</label>
                    <select
                        className="mcr-input"
                        value={importForm.data.strategy}
                        onChange={(e) => importForm.setData('strategy', e.target.value as 'skip' | 'update')}
                    >
                        <option value="skip">Lewati yang sudah ada</option>
                        <option value="update">Update yang sudah ada</option>
                    </select>
                    <InputError message={importForm.errors.strategy} />
                </form>
            </CrudModal>
        </AppLayout>
    );
}
