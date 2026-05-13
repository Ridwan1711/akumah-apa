import { Head, router, useForm } from '@inertiajs/react';
import { Download, FileText, FileUp, Pencil, Plus, Power, RotateCcw, Search, ShieldCheck, UserPlus, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppSelect,
    
    CrudCard,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
    openDownload
} from '@/components/manhood';
import type {SelectOption} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, ImportRun, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manajemen Guru', href: '/admin/teachers' },
];

type TeacherRow = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    is_active: boolean;
};

type EligibleUserRow = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    teachers: PaginatedData<TeacherRow>;
    importRuns: ImportRun[];
    filters: {
        search?: string;
        status?: string;
    };
};

type TeacherFormData = {
    mode: 'create' | 'assign';
    existing_user_id: string;
    name: string;
    username: string;
    email: string;
    password: string;
    is_active: boolean;
};

const initialForm: TeacherFormData = {
    mode: 'create',
    existing_user_id: '',
    name: '',
    username: '',
    email: '',
    password: '',
    is_active: true,
};

export default function TeacherIndex({ teachers, importRuns, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editing, setEditing] = useState<TeacherRow | null>(null);
    const [existingUserOptions, setExistingUserOptions] = useState<SelectOption[]>([]);
    const [isLoadingExistingUsers, setIsLoadingExistingUsers] = useState(false);
    const form = useForm<TeacherFormData>(initialForm);
    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'skip',
    });

    const [selectedTeacherIds, setSelectedTeacherIds] = useState<number[]>([]);
    const [bulkAssignOpen, setBulkAssignOpen] = useState(false);
    const [bulkEligibleUsers, setBulkEligibleUsers] = useState<EligibleUserRow[]>([]);
    const [bulkEligibleLoading, setBulkEligibleLoading] = useState(false);
    const [bulkUserSearch, setBulkUserSearch] = useState('');
    const [selectedBulkUserIds, setSelectedBulkUserIds] = useState<number[]>([]);
    const [bulkAssignActive, setBulkAssignActive] = useState(true);
    const bulkSetActiveForm = useForm<{ teacher_ids: number[]; is_active: boolean }>({
        teacher_ids: [],
        is_active: true,
    });
    const bulkAssignForm = useForm<{ user_ids: number[]; is_active: boolean }>({
        user_ids: [],
        is_active: true,
    });

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedSearch(search), 300);
        return () => window.clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        router.get(
            '/admin/teachers',
            {
                search: debouncedSearch || undefined,
                status: statusFilter || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [debouncedSearch, statusFilter]);

    useEffect(() => {
        setSelectedTeacherIds([]);
    }, [debouncedSearch, statusFilter, teachers.current_page]);

    const activeCount = useMemo(
        () => teachers.data.filter((item) => item.is_active).length,
        [teachers.data],
    );
    const statusOptions = useMemo<SelectOption[]>(
        () => [
            { value: 'active', label: 'Aktif' },
            { value: 'inactive', label: 'Nonaktif' },
        ],
        [],
    );
    const selectedStatusOption = statusOptions.find((item) => item.value === statusFilter) ?? null;
    const selectedExistingUserOption =
        existingUserOptions.find((item) => item.value === form.data.existing_user_id) ?? null;

    async function loadEligibleUsers(searchTerm = '') {
        setIsLoadingExistingUsers(true);
        try {
            const url = new URL('/admin/teachers/eligible-users', window.location.origin);
            if (searchTerm.trim() !== '') {
                url.searchParams.set('search', searchTerm.trim());
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            const options: SelectOption[] = (payload?.data ?? []).map((user: { id: number; name: string; email: string }) => ({
                value: String(user.id),
                label: `${user.name} (${user.email})`,
            }));
            setExistingUserOptions(options);
        } catch {
            setExistingUserOptions([]);
        } finally {
            setIsLoadingExistingUsers(false);
        }
    }

    async function loadEligibleUsersForBulk(searchTerm = '') {
        setBulkEligibleLoading(true);
        try {
            const url = new URL('/admin/teachers/eligible-users', window.location.origin);
            url.searchParams.set('limit', '200');
            if (searchTerm.trim() !== '') {
                url.searchParams.set('search', searchTerm.trim());
            }
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            setBulkEligibleUsers((payload?.data ?? []) as EligibleUserRow[]);
        } catch {
            setBulkEligibleUsers([]);
        } finally {
            setBulkEligibleLoading(false);
        }
    }

    useEffect(() => {
        if (!bulkAssignOpen) {
            return;
        }
        const delay = bulkUserSearch.trim() === '' ? 0 : 280;
        const timer = window.setTimeout(() => {
            void loadEligibleUsersForBulk(bulkUserSearch);
        }, delay);
        return () => window.clearTimeout(timer);
    }, [bulkUserSearch, bulkAssignOpen]);

    function openCreateModal() {
        setEditing(null);
        form.setData(initialForm);
        form.clearErrors();
        setCreateOpen(true);
        void loadEligibleUsers();
    }

    function openEditModal(teacher: TeacherRow) {
        setEditing(teacher);
        form.clearErrors();
        form.setData({
            mode: 'create',
            existing_user_id: '',
            name: teacher.name,
            username: teacher.username ?? '',
            email: teacher.email,
            password: '',
            is_active: teacher.is_active,
        });
    }

    function closeModal() {
        setEditing(null);
        setCreateOpen(false);
        form.clearErrors();
        form.reset();
    }

    function submitForm(e: React.FormEvent) {
        e.preventDefault();
        if (editing) {
            form.put(`/admin/teachers/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    toast.success('Data guru berhasil diperbarui.');
                },
                onError: () => toast.error('Gagal memperbarui data guru.'),
            });
            return;
        }
        form.transform((data) => {
            if (data.mode === 'assign') {
                return {
                    mode: 'assign',
                    existing_user_id: data.existing_user_id,
                    is_active: data.is_active,
                };
            }

            return {
                ...data,
            };
        });
        form.post('/admin/teachers', {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Guru berhasil ditambahkan.');
            },
            onError: () => toast.error('Gagal menambahkan guru.'),
            onFinish: () => form.transform((data) => data),
        });
    }

    function submitImport(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/teachers-import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Import guru dimulai.');
            },
            onError: () => toast.error('Gagal import guru.'),
        });
    }

    function handleModeChange(option: SelectOption | null) {
        const nextMode = (option?.value as 'create' | 'assign') ?? 'create';

        form.setData((prev) => ({
            ...prev,
            mode: nextMode,
            existing_user_id: nextMode === 'assign' ? prev.existing_user_id : '',
            name: nextMode === 'assign' ? '' : prev.name,
            username: nextMode === 'assign' ? '' : prev.username,
            email: nextMode === 'assign' ? '' : prev.email,
            password: '',
        }));
        if (nextMode === 'assign') {
            void loadEligibleUsers();
        }
    }

    function toggleActive(teacher: TeacherRow) {
        router.post(`/admin/teachers/${teacher.id}/toggle-active`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Status guru diperbarui.');
            },
            onError: () => toast.error('Gagal memperbarui status guru.'),
        });
    }

    function retryImport(runId: number) {
        router.post(`/admin/teachers-import-runs/${runId}/retry`, undefined, {
            preserveScroll: true,
            onSuccess: () => toast.success('Retry import guru dijalankan.'),
            onError: () => toast.error('Gagal retry import guru.'),
        });
    }

    function resetFilters() {
        setSearch('');
        setStatusFilter('');
    }

    const allTeachersOnPageSelected =
        teachers.data.length > 0 && teachers.data.every((t) => selectedTeacherIds.includes(t.id));

    function toggleTeacherRow(id: number) {
        setSelectedTeacherIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAllTeachersOnPage() {
        if (allTeachersOnPageSelected) {
            setSelectedTeacherIds((prev) => prev.filter((id) => !teachers.data.some((t) => t.id === id)));
        } else {
            setSelectedTeacherIds((prev) => {
                const rest = prev.filter((id) => !teachers.data.some((t) => t.id === id));
                return [...rest, ...teachers.data.map((t) => t.id)];
            });
        }
    }

    function openBulkAssignModal() {
        setBulkAssignOpen(true);
        setSelectedBulkUserIds([]);
        setBulkUserSearch('');
        setBulkAssignActive(true);
        void loadEligibleUsersForBulk('');
    }

    function closeBulkAssignModal() {
        setBulkAssignOpen(false);
        setSelectedBulkUserIds([]);
        setBulkUserSearch('');
        bulkAssignForm.clearErrors();
    }

    const allBulkUsersSelected =
        bulkEligibleUsers.length > 0 &&
        bulkEligibleUsers.every((u) => selectedBulkUserIds.includes(u.id));

    function toggleBulkUserRow(id: number) {
        setSelectedBulkUserIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAllBulkUsers() {
        if (allBulkUsersSelected) {
            setSelectedBulkUserIds([]);
        } else {
            setSelectedBulkUserIds(bulkEligibleUsers.map((u) => u.id));
        }
    }

    function submitBulkSetActive(is_active: boolean) {
        if (selectedTeacherIds.length === 0) {
            toast.error('Pilih minimal satu guru di tabel.');
            return;
        }
        if (selectedTeacherIds.length > 100) {
            toast.error('Maksimal 100 guru per aksi bulk.');
            return;
        }
        bulkSetActiveForm.setData({ teacher_ids: selectedTeacherIds, is_active });
        bulkSetActiveForm.post('/admin/teachers/bulk-set-active', {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedTeacherIds([]);
                toast.success(is_active ? 'Guru terpilih diaktifkan.' : 'Guru terpilih dinonaktifkan.');
            },
            onError: () => toast.error('Gagal memperbarui status massal.'),
        });
    }

    function submitBulkAssign(e: React.FormEvent) {
        e.preventDefault();
        if (selectedBulkUserIds.length === 0) {
            toast.error('Pilih minimal satu user.');
            return;
        }
        if (selectedBulkUserIds.length > 100) {
            toast.error('Maksimal 100 user per aksi bulk.');
            return;
        }
        bulkAssignForm.setData({ user_ids: selectedBulkUserIds, is_active: bulkAssignActive });
        bulkAssignForm.post('/admin/teachers/bulk-assign', {
            preserveScroll: true,
            onSuccess: () => {
                closeBulkAssignModal();
                toast.success('User ditetapkan sebagai guru.');
            },
            onError: () => toast.error('Gagal assign massal.'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen Guru" />
            <div>
                <CrudPageHeader
                    title="Manajemen Guru"
                    description="Kelola akun Guru secara terpisah dari manajemen user umum."
                />

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Guru', value: teachers.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'active', label: 'Guru Aktif (Halaman)', value: activeCount, icon: <ShieldCheck size={18} />, tone: 'green' },
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
                                    placeholder="Cari nama, username, email..."
                                />
                            </div>
                            <div style={{ minWidth: 220 }}>
                                <AppSelect
                                    placeholder="Filter status..."
                                    options={statusOptions}
                                    value={selectedStatusOption}
                                    onChange={(option) => setStatusFilter(String(option?.value ?? ''))}
                                />
                            </div>
                            {(search || statusFilter) && (
                                <button type="button" className="mcr-btn ghost" onClick={resetFilters}>
                                    <RotateCcw size={14} />
                                    Reset
                                </button>
                            )}
                        </>
                    }
                    right={
                        <>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload('/admin/teachers-template?format=xlsx')}
                            >
                                <FileText size={14} />
                                Template
                            </button>
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload(`/admin/teachers-export?format=xlsx&search=${encodeURIComponent(debouncedSearch)}&status=${statusFilter}`)}
                            >
                                <Download size={14} />
                                Export
                            </button>
                            <button type="button" className="mcr-btn secondary" onClick={() => setImportOpen(true)}>
                                <FileUp size={14} />
                                Import
                            </button>
                            <button type="button" className="mcr-btn secondary" onClick={openBulkAssignModal}>
                                <UserPlus size={14} />
                                Bulk user → Guru
                            </button>
                            <button type="button" className="mcr-btn primary" onClick={openCreateModal}>
                                <Plus size={14} />
                                Tambah Guru
                            </button>
                        </>
                    }
                />

                {selectedTeacherIds.length > 0 ? (
                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            alignItems: 'center',
                            gap: 10,
                            marginBottom: 12,
                            padding: '10px 14px',
                            borderRadius: 10,
                            border: '1px solid #e2e8f0',
                            background: '#f1f5f9',
                        }}
                    >
                        <span style={{ fontWeight: 600, fontSize: 13 }}>
                            {selectedTeacherIds.length} guru dipilih
                        </span>
                        <button type="button" className="mcr-btn ghost" onClick={() => setSelectedTeacherIds([])}>
                            Bersihkan pilihan
                        </button>
                        <button
                            type="button"
                            className="mcr-btn primary"
                            disabled={bulkSetActiveForm.processing}
                            onClick={() => submitBulkSetActive(true)}
                        >
                            Aktifkan terpilih
                        </button>
                        <button
                            type="button"
                            className="mcr-btn danger"
                            disabled={bulkSetActiveForm.processing}
                            onClick={() => submitBulkSetActive(false)}
                        >
                            Nonaktifkan terpilih
                        </button>
                    </div>
                ) : null}

                <CrudTableShell>
                    <table className="mcr-table">
                        <thead>
                            <tr>
                                <th style={{ width: 40 }}>
                                    <input
                                        type="checkbox"
                                        className="mcr-check"
                                        checked={allTeachersOnPageSelected}
                                        onChange={toggleAllTeachersOnPage}
                                        aria-label="Pilih semua guru di halaman ini"
                                    />
                                </th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {teachers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <CrudEmptyState
                                            title="Belum ada data guru"
                                            description="Tambahkan guru baru untuk mulai penugasan mapel."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                teachers.data.map((teacher) => (
                                    <tr key={teacher.id}>
                                        <td>
                                            <input
                                                type="checkbox"
                                                className="mcr-check"
                                                checked={selectedTeacherIds.includes(teacher.id)}
                                                onChange={() => toggleTeacherRow(teacher.id)}
                                                aria-label={`Pilih ${teacher.name}`}
                                            />
                                        </td>
                                        <td>{teacher.name}</td>
                                        <td>{teacher.username ?? '-'}</td>
                                        <td>{teacher.email}</td>
                                        <td>
                                            <span className={`mcr-dot-badge ${teacher.is_active ? 'active' : 'keluar'}`}>
                                                {teacher.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="mcr-action-group">
                                                <button
                                                    type="button"
                                                    className="mcr-icon-action"
                                                    title="Edit"
                                                    onClick={() => openEditModal(teacher)}
                                                >
                                                    <Pencil size={13} />
                                                </button>
                                                <button
                                                    type="button"
                                                    className={`mcr-icon-action ${teacher.is_active ? 'danger' : ''}`}
                                                    title={teacher.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                    onClick={() => toggleActive(teacher)}
                                                >
                                                    <Power size={13} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CrudTableShell>

                <CrudPagination links={teachers.links} />

                <CrudCard title="Riwayat Import Guru" subtitle="Pantau import guru dari file XLSX/CSV.">
                    {importRuns.length === 0 ? (
                        <CrudEmptyState title="Belum ada riwayat import" description="Import guru akan muncul di sini." />
                    ) : (
                        <div style={{ display: 'grid', gap: 10 }}>
                            {importRuns.map((run) => (
                                <div key={run.id} className="mcr-run-item">
                                    <div className="mcr-run-top">
                                        <div>
                                            <strong>{run.file_name}</strong>
                                            <div className="mcr-run-meta">Strategi {run.strategy.toUpperCase()} • {run.processed_rows}/{run.total_rows || '-'}</div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <span className={`mcr-dot-badge ${run.status === 'completed' ? 'active' : run.status === 'failed' ? 'wafat' : 'keluar'}`}>
                                                {run.status}
                                            </span>
                                            {run.error_report_path ? (
                                                <a href={`/admin/teachers-import-errors/${run.uuid}`} className="mcr-btn secondary">
                                                    Error CSV
                                                </a>
                                            ) : null}
                                            {run.status === 'failed' ? (
                                                <button type="button" className="mcr-btn secondary" onClick={() => retryImport(run.id)}>
                                                    <RotateCcw size={14} />
                                                    Retry
                                                </button>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className="mcr-run-stats">
                                        <span>C:{run.created_count}</span>
                                        <span>U:{run.updated_count}</span>
                                        <span>S:{run.skipped_count}</span>
                                        <span>F:{run.failed_count}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CrudCard>
            </div>

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Import Data Guru"
                subtitle="Unduh template dulu, lalu unggah file XLSX/CSV sesuai format."
            >
                <form onSubmit={submitImport}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() => openDownload('/admin/teachers-template?format=xlsx')}
                            >
                                <FileText size={14} />
                                Download Template XLSX
                            </button>
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="teacher-import-file">File XLSX/CSV</label>
                            <input
                                id="teacher-import-file"
                                className="mcr-input"
                                type="file"
                                accept=".xlsx,.csv"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={importForm.errors.file} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="teacher-import-strategy">Strategi Duplikat Email</label>
                            <select
                                id="teacher-import-strategy"
                                className="mcr-form-select"
                                value={importForm.data.strategy}
                                onChange={(e) => importForm.setData('strategy', e.target.value as 'skip' | 'update')}
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

            <CrudModal
                open={bulkAssignOpen}
                onClose={closeBulkAssignModal}
                title="Bulk assign: user → Guru"
                subtitle="Pilih banyak user yang belum punya role Guru (maks. 200 daftar). Role Guru ditambahkan tanpa menghapus role lain."
            >
                <form onSubmit={submitBulkAssign}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="bulk-teacher-search">Cari nama / email</label>
                            <input
                                id="bulk-teacher-search"
                                className="mcr-input"
                                type="search"
                                value={bulkUserSearch}
                                onChange={(e) => setBulkUserSearch(e.target.value)}
                                placeholder="Ketik untuk memfilter..."
                            />
                        </div>
                        <div className="mcr-form-group full" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <input
                                id="bulk-assign-active"
                                type="checkbox"
                                className="mcr-check"
                                checked={bulkAssignActive}
                                onChange={(e) => setBulkAssignActive(e.target.checked)}
                            />
                            <label htmlFor="bulk-assign-active" style={{ fontSize: 13, cursor: 'pointer' }}>
                                Set akun terpilih sebagai aktif setelah ditetapkan jadi guru
                            </label>
                        </div>
                        <InputError message={bulkAssignForm.errors.user_ids} />
                        <div
                            style={{
                                maxHeight: 280,
                                overflow: 'auto',
                                border: '1px solid #e2e8f0',
                                borderRadius: 8,
                                background: '#fff',
                            }}
                        >
                            {bulkEligibleLoading ? (
                                <div style={{ padding: 16, textAlign: 'center', fontSize: 13 }}>Memuat daftar…</div>
                            ) : bulkEligibleUsers.length === 0 ? (
                                <div style={{ padding: 16, textAlign: 'center', fontSize: 13, color: '#64748b' }}>
                                    Tidak ada user eligible (semua sudah guru atau tidak cocok filter).
                                </div>
                            ) : (
                                <table className="mcr-table" style={{ margin: 0 }}>
                                    <thead>
                                        <tr>
                                            <th style={{ width: 40 }}>
                                                <input
                                                    type="checkbox"
                                                    className="mcr-check"
                                                    checked={allBulkUsersSelected}
                                                    onChange={toggleAllBulkUsers}
                                                    aria-label="Pilih semua di daftar"
                                                />
                                            </th>
                                            <th>Nama</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {bulkEligibleUsers.map((u) => (
                                            <tr key={u.id}>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        className="mcr-check"
                                                        checked={selectedBulkUserIds.includes(u.id)}
                                                        onChange={() => toggleBulkUserRow(u.id)}
                                                        aria-label={`Pilih ${u.name}`}
                                                    />
                                                </td>
                                                <td>{u.name}</td>
                                                <td>{u.email}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        <p style={{ fontSize: 12, color: '#64748b', margin: 0 }}>
                            Terpilih: {selectedBulkUserIds.length} user (maks. 100 per submit).
                        </p>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={closeBulkAssignModal}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={bulkAssignForm.processing}>
                            {bulkAssignForm.processing ? 'Menyimpan…' : 'Tetapkan sebagai guru'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal
                open={createOpen || editing !== null}
                onClose={closeModal}
                title={editing ? 'Edit Guru' : 'Tambah Guru'}
                subtitle="Akun yang dibuat di sini otomatis memiliki role Guru."
            >
                <form onSubmit={submitForm}>
                    <div className="mcr-form-grid">
                        {!editing && (
                            <>
                                <div className="mcr-form-group">
                                    <label htmlFor="teacher-mode">Metode Tambah</label>
                                    <AppSelect
                                        inputId="teacher-mode"
                                        options={[
                                            { value: 'create', label: 'Buat akun baru' },
                                            { value: 'assign', label: 'Assign dari user yang ada' },
                                        ]}
                                        value={
                                            form.data.mode === 'assign'
                                                ? { value: 'assign', label: 'Assign dari user yang ada' }
                                                : { value: 'create', label: 'Buat akun baru' }
                                        }
                                        onChange={handleModeChange}
                                    />
                                    <InputError message={form.errors.mode} />
                                </div>
                                {form.data.mode === 'assign' && (
                                    <div className="mcr-form-group full">
                                        <label htmlFor="existing-user">Pilih User Existing</label>
                                        <AppSelect
                                            inputId="existing-user"
                                            placeholder="Pilih user..."
                                            options={existingUserOptions}
                                            isLoading={isLoadingExistingUsers}
                                            value={selectedExistingUserOption}
                                            onChange={(option) => form.setData('existing_user_id', String(option?.value ?? ''))}
                                            onInputChange={(value, meta) => {
                                                if (meta.action === 'input-change') {
                                                    void loadEligibleUsers(value);
                                                }
                                                return value;
                                            }}
                                            onMenuOpen={() => {
                                                if (existingUserOptions.length === 0) {
                                                    void loadEligibleUsers();
                                                }
                                            }}
                                        />
                                        <InputError message={form.errors.existing_user_id} />
                                    </div>
                                )}
                            </>
                        )}
                        {(editing || form.data.mode === 'create') && (
                            <>
                        <div className="mcr-form-group">
                            <label htmlFor="teacher-name">Nama</label>
                            <input
                                id="teacher-name"
                                className="mcr-input"
                                autoComplete="off"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="teacher-username">Username (opsional)</label>
                            <input
                                id="teacher-username"
                                className="mcr-input"
                                autoComplete="off"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
                            />
                            <InputError message={form.errors.username} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="teacher-email">Email</label>
                            <input
                                id="teacher-email"
                                type="email"
                                className="mcr-input"
                                autoComplete="off"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        {!editing && (
                            <div className="mcr-form-group">
                                <label htmlFor="teacher-password">Password</label>
                                <input
                                    id="teacher-password"
                                    type="password"
                                    className="mcr-input"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                />
                                <InputError message={form.errors.password} />
                            </div>
                        )}
                            </>
                        )}
                        <div className="mcr-form-group">
                            <label htmlFor="teacher-active">Status</label>
                            <AppSelect
                                inputId="teacher-active"
                                options={[
                                    { value: '1', label: 'Aktif' },
                                    { value: '0', label: 'Nonaktif' },
                                ]}
                                value={form.data.is_active ? { value: '1', label: 'Aktif' } : { value: '0', label: 'Nonaktif' }}
                                onChange={(option) => form.setData('is_active', String(option?.value ?? '1') === '1')}
                            />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={closeModal}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
