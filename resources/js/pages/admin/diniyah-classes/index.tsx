import { Head, router, useForm } from '@inertiajs/react';
import { Download, Edit, FileText, FileUp, GraduationCap, Hash, Plus, Search, Trash2, Users } from 'lucide-react';
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
    openDownload,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, SchoolClass } from '@/types';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kelas Diniyah', href: '/admin/diniyah-classes' },
];

type GradeLevel = { id: number; name: string; order: number };

type ClassRow = SchoolClass & {
    grade_level?: GradeLevel;
    students_count?: number;
};

const studentGenderLabels: Record<string, string> = {
    L: 'Santriyyin',
    P: 'Santriyah',
};

type Props = {
    classes: PaginatedData<ClassRow>;
    gradeLevels: GradeLevel[];
    filters: { per_page?: string; search?: string };
    perPageOptions: number[];
};

export default function DiniyahClassIndex({
    classes,
    gradeLevels,
    filters,
    perPageOptions,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [classToDelete, setClassToDelete] = useState<ClassRow | null>(null);
    const [editingClass, setEditingClass] = useState<ClassRow | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const currentPerPage = filters.per_page ?? String(perPageOptions[0] ?? 25);

    const form = useForm({
        name: '',
        grade_level_id: '',
        order: '0',
        student_gender: '' as string,
    });
    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'skip',
    });

    function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/diniyah-classes', {
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
                toast.success('Kelas berhasil ditambahkan');
            },
            onError: () => toast.error('Gagal menambah kelas'),
        });
    }

    function handleEdit(cls: ClassRow) {
        setEditingClass(cls);
        setDialogOpen(true);
        form.setData({
            name: cls.name,
            grade_level_id: String(cls.grade_level_id ?? cls.grade_level?.id ?? ''),
            order: String((cls as ClassRow & { order?: number; level_order?: number }).order ?? cls.level_order ?? 0),
            student_gender: cls.student_gender ?? '',
        });
    }

    function handleUpdate(cls: ClassRow, e: React.FormEvent) {
        e.preventDefault();
        form.put(`/admin/diniyah-classes/${cls.id}`, {
            onSuccess: () => {
                setDialogOpen(false);
                setEditingClass(null);
                form.reset();
                toast.success('Kelas berhasil diperbarui');
            },
            onError: () => toast.error('Gagal memperbarui kelas'),
        });
    }

    function handleDeleteRequest(item: ClassRow) {
        setClassToDelete(item);
        setDeleteDialogOpen(true);
    }

    function handleDeleteConfirm() {
        if (!classToDelete) return;
        router.delete(`/admin/diniyah-classes/${classToDelete.id}`, {
            onSuccess: () => toast.success('Kelas berhasil dihapus'),
            onError: () => toast.error('Gagal menghapus kelas'),
            onFinish: () => {
                setDeleteDialogOpen(false);
                setClassToDelete(null);
            },
        });
    }

    function handlePerPageChange(value: string) {
        router.get(
            '/admin/diniyah-classes',
            { per_page: value, page: 1, search: search || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleSearchSubmit() {
        router.get(
            '/admin/diniyah-classes',
            { search: search || undefined, per_page: currentPerPage, page: 1 },
            { preserveState: true, preserveScroll: true },
        );
    }

    function openCreate() {
        setEditingClass(null);
        form.clearErrors();
        form.setData({
            name: '',
            grade_level_id: '',
            order: '0',
            student_gender: '',
        });
        setDialogOpen(true);
    }

    function handleImportSubmit(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/diniyah-classes-import', {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Import kelas selesai diproses');
            },
            onError: () => toast.error('Gagal mengimport kelas'),
        });
    }

    const totalStudents = useMemo(
        () => classes.data.reduce((sum, row) => sum + (row.students_count ?? 0), 0),
        [classes.data],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelas Diniyah" />
            <div>
                <CrudPageHeader
                    title="Kelas Diniyah"
                    description="Kelola struktur kelas, jenjang, urutan, dan segmentasi santri."
                />

                <CrudStatStrip
                    items={[
                        {
                            key: 'total',
                            label: 'Total Kelas',
                            value: classes.total,
                            icon: <GraduationCap size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'rows',
                            label: 'Kelas Halaman Ini',
                            value: classes.data.length,
                            icon: <Hash size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'students',
                            label: 'Total Santri (Halaman)',
                            value: totalStudents,
                            icon: <Users size={18} />,
                            tone: 'amber',
                        },
                        {
                            key: 'grade',
                            label: 'Jumlah Jenjang',
                            value: gradeLevels.length,
                            icon: <Search size={18} />,
                            tone: 'purple',
                        },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input
                                    placeholder="Cari nama kelas..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleSearchSubmit();
                                        }
                                    }}
                                />
                            </div>
                            <select
                                className="mcr-filter-select"
                                value={currentPerPage}
                                onChange={(e) => handlePerPageChange(e.target.value)}
                            >
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>
                                        {opt} / halaman
                                    </option>
                                ))}
                            </select>
                            <button type="button" className="mcr-btn secondary" onClick={handleSearchSubmit}>
                                Cari
                            </button>
                        </>
                    }
                    right={
                        <>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() =>
                                    openDownload(
                                        `/admin/diniyah-classes-template?format=xlsx`,
                                    )
                                }
                            >
                                <FileText size={14} />
                                Template
                            </button>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() =>
                                    openDownload(
                                        `/admin/diniyah-classes-export?format=xlsx&search=${encodeURIComponent(search)}`,
                                    )
                                }
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
                                Tambah Kelas
                            </button>
                        </>
                    }
                />

                {classes.data.length === 0 ? (
                    <CrudCard>
                        <CrudEmptyState
                            title="Belum ada kelas diniyah"
                            description="Tambahkan kelas pertama untuk mulai melakukan pengelolaan akademik."
                        />
                    </CrudCard>
                ) : (
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Nama Kelas</th>
                                    <th>Jenjang</th>
                                    <th>Urutan</th>
                                    <th>Jenis Santri</th>
                                    <th>Jumlah Santri</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {classes.data.map((cls) => (
                                    <tr key={cls.id}>
                                        <td>
                                            <div className="mcr-student-cell">
                                                <span className="mcr-avatar">
                                                    {cls.name
                                                        .split(' ')
                                                        .slice(0, 2)
                                                        .map((word) => word[0] ?? '')
                                                        .join('')
                                                        .toUpperCase()}
                                                </span>
                                                <div>
                                                    <div className="name">{cls.name}</div>
                                                    <div className="sub">ID #{cls.id}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{cls.grade_level?.name ?? '-'}</td>
                                        <td>{(cls as ClassRow & { order?: number; level_order?: number }).order ?? cls.level_order ?? '-'}</td>
                                        <td>
                                            {cls.student_gender ? (
                                                <span className="mcr-dot-badge active">
                                                    {cls.student_gender_label ??
                                                        studentGenderLabels[cls.student_gender] ??
                                                        cls.student_gender}
                                                </span>
                                            ) : (
                                                <span className="mcr-dot-badge keluar">Belum diatur</span>
                                            )}
                                        </td>
                                        <td>{cls.students_count ?? 0}</td>
                                        <td>
                                            <div className="mcr-action-group">
                                                <button
                                                    type="button"
                                                    className="mcr-icon-action"
                                                    onClick={() => handleEdit(cls)}
                                                    title="Edit kelas"
                                                >
                                                    <Edit size={13} />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="mcr-icon-action danger"
                                                    onClick={() => handleDeleteRequest(cls)}
                                                    title="Hapus kelas"
                                                >
                                                    <Trash2 size={13} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CrudTableShell>
                )}

                <CrudPagination links={classes.links} />
            </div>

            <CrudModal
                open={dialogOpen}
                onClose={() => {
                    setDialogOpen(false);
                    setEditingClass(null);
                }}
                title={editingClass ? 'Edit Kelas Diniyah' : 'Tambah Kelas Diniyah'}
                subtitle="Atur nama kelas, jenjang, urutan, dan segmentasi santri."
            >
                <form
                    onSubmit={
                        editingClass
                            ? (e: React.FormEvent) => handleUpdate(editingClass, e)
                            : handleCreate
                    }
                >
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="class-name">Nama Kelas</label>
                            <input
                                id="class-name"
                                className="mcr-input"
                                placeholder="Ula 1A"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="class-grade-level">Jenjang</label>
                            <select
                                id="class-grade-level"
                                className="mcr-form-select"
                                value={form.data.grade_level_id}
                                onChange={(e) => form.setData('grade_level_id', e.target.value)}
                            >
                                <option value="">Pilih jenjang</option>
                                {gradeLevels.map((gl) => (
                                    <option key={gl.id} value={String(gl.id)}>
                                        {gl.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.grade_level_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="class-level-order">Urutan dalam jenjang</label>
                            <input
                                id="class-level-order"
                                className="mcr-input"
                                type="number"
                                min={0}
                                max={1000}
                                value={form.data.order}
                                onChange={(e) => form.setData('order', e.target.value)}
                            />
                            <InputError message={form.errors.order} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="class-gender">Jenis kelamin santri</label>
                            <select
                                id="class-gender"
                                className="mcr-form-select"
                                value={form.data.student_gender ? form.data.student_gender : '_none'}
                                onChange={(e) =>
                                    form.setData(
                                        'student_gender',
                                        e.target.value === '_none' ? '' : e.target.value,
                                    )
                                }
                            >
                                <option value="_none">— Pilih —</option>
                                <option value="L">{studentGenderLabels.L}</option>
                                <option value="P">{studentGenderLabels.P}</option>
                            </select>
                            <InputError message={form.errors.student_gender} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button
                            type="button"
                            className="mcr-btn ghost"
                            onClick={() => {
                                setDialogOpen(false);
                                setEditingClass(null);
                            }}
                        >
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteDialogOpen}
                onClose={() => {
                    setDeleteDialogOpen(false);
                    setClassToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                title="Konfirmasi Hapus Kelas"
                description={`Hapus kelas "${classToDelete?.name ?? '-'}"?`}
                confirmLabel="Hapus Kelas"
            />

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Import Kelas Diniyah"
                subtitle="Unggah file CSV/XLSX, lalu pilih strategi jika nama kelas sudah ada."
            >
                <form onSubmit={handleImportSubmit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="import-class-file">File Import</label>
                            <input
                                id="import-class-file"
                                className="mcr-input"
                                type="file"
                                accept=".xlsx,.csv"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={importForm.errors.file} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="import-class-strategy">Strategi data duplikat (nama kelas)</label>
                            <select
                                id="import-class-strategy"
                                className="mcr-form-select"
                                value={importForm.data.strategy}
                                onChange={(e) =>
                                    importForm.setData('strategy', e.target.value as 'skip' | 'update')
                                }
                            >
                                <option value="skip">Lewati data yang sudah ada</option>
                                <option value="update">Perbarui data yang sudah ada</option>
                            </select>
                            <InputError message={importForm.errors.strategy} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setImportOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={importForm.processing}>
                            {importForm.processing ? 'Memproses...' : 'Proses Import'}
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}