import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Search, ShieldCheck, ShieldX, Trash2, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppMultiSelect,
    
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
    AppSelect
} from '@/components/manhood';
import type {SelectOption} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, Student, StudentPosition } from '@/types';

const positionTypes: { id: number; name: string; code: string }[] = [
    { id: 1, name: 'Keamanan', code: "KMN" },
    { id: 2, name: 'Kebersihan', code: "KBH" },
    { id: 3, name: 'Pendidikan', code: "PDK" },
    { id: 4, name: 'Kesehatan', code: "KHS" },
    { id: 5, name: 'Kantin', code: "KNT" },
    { id: 6, name: 'Kolektor', code: "KLT" },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Posisi Pengurus Santri', href: '/admin/student-positions' },
];

type Props = {
    positions: PaginatedData<StudentPosition>;
    students: Pick<Student, 'id' | 'full_name' | 'nis'>[];
    divisionOptions: string[];
    financeUsers: {
        id: number;
        name: string;
        username: string | null;
        email: string;
        division_codes: string[];
    }[];
    filters: {
        search?: string;
        status?: string;
        division_code?: string;
    };
};

type PositionFormData = {
    student_id: string;
    position_type: string;
    started_at: string;
    ended_at: string;
    is_active: boolean;
};

const defaultFormData: PositionFormData = {
    student_id: '',
    position_type: '',
    started_at: '',
    ended_at: '',
    is_active: true,
};

export default function StudentPositionIndex({ positions, students, divisionOptions, financeUsers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [divisionFilter, setDivisionFilter] = useState(filters.division_code ?? '');
    const [modalOpen, setModalOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [editing, setEditing] = useState<StudentPosition | null>(null);
    const [deleting, setDeleting] = useState<StudentPosition | null>(null);
    const [savingScopeUserId, setSavingScopeUserId] = useState<number | null>(null);
    const [scopeAssignments, setScopeAssignments] = useState<Record<number, string[]>>({});

    const form = useForm<PositionFormData>(defaultFormData);

    useEffect(() => {
        const next: Record<number, string[]> = {};
        financeUsers.forEach((user) => {
            next[user.id] = user.division_codes ?? [];
        });
        setScopeAssignments(next);
    }, [financeUsers]);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedSearch(search), 300);
        return () => window.clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        router.get(
            '/admin/student-positions',
            {
                search: debouncedSearch || undefined,
                status: statusFilter || undefined,
                division_code: divisionFilter || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [debouncedSearch, statusFilter, divisionFilter]);

    const activeCount = useMemo(
        () => positions.data.filter((item) => item.is_active).length,
        [positions.data],
    );

    const divisionCount = useMemo(() => {
        return new Set(
            positions.data
                .map((item) => item.division_code)
                .filter((code): code is string => !!code && code.trim().length > 0),
        ).size;
    }, [positions.data]);

    const divisionSelectOptions = useMemo<SelectOption[]>(
        () =>
            divisionOptions.map((code) => ({
                value: code,
                label: code,
            })),
        [divisionOptions],
    );
    const studentOptions = useMemo<SelectOption[]>(
        () => students.map((student) => ({ value: student.id, label: `${student.full_name} (${student.nis})` })),
        [students],
    );
    const positionOptions = useMemo<SelectOption[]>(
        () => positionTypes.map((position) => ({ value: position.name, label: position.name })),
        [],
    );

    function openCreateModal() {
        setEditing(null);
        form.setData(defaultFormData);
        form.clearErrors();
        setModalOpen(true);
    }

    function openEditModal(item: StudentPosition) {
        setEditing(item);
        form.setData({
            student_id: String(item.student_id),
            position_type: item.position_type ?? '',
            started_at: item.started_at ?? '',
            ended_at: item.ended_at ?? '',
            is_active: item.is_active,
        });
        form.clearErrors();
        setModalOpen(true);
    }

    function submitForm(e: React.FormEvent) {
        e.preventDefault();
        const payload = {
            ...form.data,
            student_id: Number(form.data.student_id),
            division_code: form.data.position_type || null,
            started_at: form.data.started_at || null,
            ended_at: form.data.ended_at || null,
        };

        if (editing) {
            form.transform(() => payload);
            form.put(`/admin/student-positions/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    toast.success('Posisi pengurus diperbarui.');
                },
                onError: () => toast.error('Gagal memperbarui posisi pengurus.'),
            });
            return;
        }

        form.transform(() => payload);
        form.post('/admin/student-positions', {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.setData(defaultFormData);
                toast.success('Posisi pengurus ditambahkan.');
            },
            onError: () => toast.error('Gagal menambahkan posisi pengurus.'),
        });
    }

    function confirmDelete(item: StudentPosition) {
        setDeleting(item);
        setDeleteOpen(true);
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(`/admin/student-positions/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteOpen(false);
                setDeleting(null);
                toast.success('Posisi pengurus dihapus.');
            },
            onError: () => toast.error('Gagal menghapus posisi pengurus.'),
        });
    }

    function toggleActive(item: StudentPosition) {
        const action = item.is_active ? 'deactivate' : 'activate';
        router.patch(
            `/admin/student-positions/${item.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        item.is_active ? 'Posisi pengurus dinonaktifkan.' : 'Posisi pengurus diaktifkan.',
                    );
                },
                onError: () => toast.error('Gagal mengubah status posisi pengurus.'),
            },
        );
    }

    function resetFilters() {
        setSearch('');
        setStatusFilter('');
        setDivisionFilter('');
    }

    function handleScopeChange(userId: number, next: readonly SelectOption[] | null) {
        setScopeAssignments((prev) => ({
            ...prev,
            [userId]: (next ?? []).map((item) => String(item.value)),
        }));
    }

    function saveDivisionAccess(userId: number) {
        setSavingScopeUserId(userId);
        router.put(
            `/admin/student-positions/division-access/${userId}`,
            {
                division_codes: scopeAssignments[userId] ?? [],
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Scope divisi user berhasil diperbarui.'),
                onError: () => toast.error('Gagal menyimpan scope divisi user.'),
                onFinish: () => setSavingScopeUserId(null),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Posisi Pengurus Santri" />
            <div>
                <CrudPageHeader
                    title="Posisi Pengurus Santri"
                    description="Kelola penugasan pengurus santri per divisi untuk kebutuhan operasional dan filter tagihan."
                />

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Posisi', value: positions.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'active', label: 'Aktif (Halaman)', value: activeCount, icon: <ShieldCheck size={18} />, tone: 'green' },
                        { key: 'division', label: 'Divisi (Halaman)', value: divisionCount, icon: <ShieldX size={18} />, tone: 'amber' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama/NIS/posisi/divisi..."
                                />
                            </div>
                            <select
                                className="mcr-filter-select"
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                            <select
                                className="mcr-filter-select"
                                value={divisionFilter}
                                onChange={(e) => setDivisionFilter(e.target.value)}
                            >
                                <option value="">Semua Divisi</option>
                                {divisionOptions.map((code) => (
                                    <option key={code} value={code}>
                                        {code}
                                    </option>
                                ))}
                            </select>
                            {(search || statusFilter || divisionFilter) && (
                                <button type="button" className="mcr-btn ghost" onClick={resetFilters}>
                                    <RotateCcw size={14} />
                                    Reset
                                </button>
                            )}
                        </>
                    }
                    right={
                        <button type="button" className="mcr-btn primary" onClick={openCreateModal}>
                            <Plus size={14} />
                            Tambah Posisi
                        </button>
                    }
                />

                <CrudCard>
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Santri</th>
                                    <th>Posisi</th>
                                    <th>Divisi</th>
                                    <th>Periode</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {positions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            <CrudEmptyState
                                                title="Belum ada posisi pengurus"
                                                description="Tambah posisi pengurus baru untuk santri."
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    positions.data.map((item) => (
                                        <tr key={item.id}>
                                            <td>
                                                {item.student?.full_name ?? '-'} ({item.student?.nis ?? '-'})
                                                <div className="mcr-run-meta">
                                                    {item.student?.current_class?.name ?? 'Tanpa kelas'}
                                                </div>
                                            </td>
                                            <td>{item.position_type}</td>
                                            <td>{item.division_code ?? '-'}</td>
                                            <td>
                                                {item.started_at ?? '-'} s/d {item.ended_at ?? '-'}
                                            </td>
                                            <td>
                                                <span className={`mcr-dot-badge ${item.is_active ? 'active' : 'wafat'}`}>
                                                    {item.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <button
                                                        type="button"
                                                        className="mcr-icon-action"
                                                        title="Edit"
                                                        onClick={() => openEditModal(item)}
                                                    >
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="mcr-icon-action"
                                                        title={item.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                        onClick={() => toggleActive(item)}
                                                    >
                                                        {item.is_active ? <ShieldX size={13} /> : <ShieldCheck size={13} />}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="mcr-icon-action danger"
                                                        title="Hapus"
                                                        onClick={() => confirmDelete(item)}
                                                    >
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

                    <CrudPagination links={positions.links} />
                </CrudCard>

                <CrudCard
                    title="Akses Divisi Admin Keuangan"
                    subtitle="Atur division scope untuk user yang memiliki permission invoice.view_pengurus_division."
                >
                    {financeUsers.length === 0 ? (
                        <CrudEmptyState
                            title="Belum ada user dengan permission scope division"
                            description="Tambahkan permission invoice.view_pengurus_division pada user terlebih dahulu."
                        />
                    ) : divisionOptions.length === 0 ? (
                        <CrudEmptyState
                            title="Belum ada kode divisi"
                            description="Tambahkan data posisi pengurus beserta kode divisinya terlebih dahulu."
                        />
                    ) : (
                        <div style={{ display: 'grid', gap: 12 }}>
                            {financeUsers.map((user) => {
                                const selectedValues = scopeAssignments[user.id] ?? [];
                                const selectedOptions = divisionSelectOptions.filter((option) =>
                                    selectedValues.includes(String(option.value)),
                                );

                                return (
                                    <div
                                        key={user.id}
                                        style={{
                                            border: '1px solid var(--mhs-border)',
                                            borderRadius: 10,
                                            padding: 12,
                                            display: 'grid',
                                            gap: 8,
                                        }}
                                    >
                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
                                            <div>
                                                <div style={{ fontWeight: 600 }}>{user.name}</div>
                                                <div className="mcr-run-meta">
                                                    {user.username ? `@${user.username}` : user.email}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                className="mcr-btn secondary"
                                                disabled={savingScopeUserId === user.id}
                                                onClick={() => saveDivisionAccess(user.id)}
                                            >
                                                {savingScopeUserId === user.id ? 'Menyimpan...' : 'Simpan Scope'}
                                            </button>
                                        </div>
                                        <AppMultiSelect
                                            placeholder="Pilih divisi yang boleh diakses..."
                                            options={divisionSelectOptions}
                                            value={selectedOptions}
                                            onChange={(next) => handleScopeChange(user.id, next)}
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CrudCard>
            </div>

            <CrudModal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? 'Edit Posisi Pengurus' : 'Tambah Posisi Pengurus'}
                subtitle="Isi data penugasan pengurus santri."
            >
                <form onSubmit={submitForm}>
                    <div className="mcr-form">
                        <div className="mcr-form-group">
                            <label htmlFor="student_id">Santri</label>
                            <AppSelect
                                inputId="student_id"
                                placeholder="Pilih santri..."
                                options={studentOptions}
                                value={studentOptions.find((option) => String(option.value) === form.data.student_id) ?? null}
                                onChange={(option) => form.setData('student_id', String(option?.value ?? ''))}
                            />
                            <InputError message={form.errors.student_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="position_type">Jabatan</label>
                            <AppSelect
                                inputId="position_type"
                                placeholder="Pilih jabatan..."
                                options={positionOptions}
                                value={positionOptions.find((option) => option.value === form.data.position_type) ?? null}
                                onChange={(option) => form.setData('position_type', String(option?.value ?? ''))}
                            />
                            <InputError message={form.errors.position_type} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="started_at">Mulai Tugas</label>
                            <input
                                id="started_at"
                                type="date"
                                className="mcr-input"
                                value={form.data.started_at}
                                onChange={(e) => form.setData('started_at', e.target.value)}
                            />
                            <InputError message={form.errors.started_at} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="ended_at">Selesai Tugas</label>
                            <input
                                id="ended_at"
                                type="date"
                                className="mcr-input"
                                value={form.data.ended_at}
                                onChange={(e) => form.setData('ended_at', e.target.value)}
                            />
                            <InputError message={form.errors.ended_at} />
                        </div>
                        <div className="mcr-form-group full">
                            <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                Status aktif
                            </label>
                            <InputError message={form.errors.is_active} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setModalOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={handleDelete}
                title="Hapus Posisi Pengurus"
                description={`Hapus posisi "${deleting?.position_type ?? '-'}" milik ${deleting?.student?.full_name ?? 'santri'}?`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
