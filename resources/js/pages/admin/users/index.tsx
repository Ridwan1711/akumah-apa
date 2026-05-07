import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileUp,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Search,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppMultiSelect,
    
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
import { can } from '@/lib/authz';
import type { Auth, BreadcrumbItem, ImportRun, PaginatedData, Role, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manajemen User', href: '/admin/users' },
];

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    admin_akademik: 'Admin Akademik',
    admin_keuangan: 'Admin Keuangan',
    guru: 'Guru',
    musyrif: 'Musyrif',
    santri: 'Santri',
    wali_santri: 'Wali Santri',
};

type PermissionLite = { id: number; name: string };
type UserRow = {
    id: number;
    name: string;
    username: string | null;
    email: string;
    is_active: boolean;
    roles?: Pick<Role, 'id' | 'name'>[];
    permissions?: PermissionLite[];
    has_official_photo?: boolean;
};

type Props = {
    users: PaginatedData<UserRow>;
    roles: Pick<Role, 'id' | 'name'>[];
    permissions: PermissionLite[];
    filters: {
        search?: string;
        role_ids?: number[];
        status?: string;
        role_name?: string;
        import_uploader_id?: string;
        has_official_photo?: string;
    };
    photoCompliance?: {
        with_official_photo: number;
        without_official_photo: number;
    };
    isTeacherMode?: boolean;
    importRuns: ImportRun[];
    importUploaders: Pick<User, 'id' | 'name'>[];
};

type UserFormData = {
    name: string;
    username: string;
    email: string;
    password: string;
    role_ids: number[];
    permission_ids: number[];
    is_active: boolean;
};

const initialUserForm: UserFormData = {
    name: '',
    username: '',
    email: '',
    password: '',
    role_ids: [],
    permission_ids: [],
    is_active: true,
};

export default function UserIndex({
    users,
    roles,
    permissions,
    filters,
    isTeacherMode,
    importRuns,
    importUploaders,
}: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canManageUsers = can(auth, 'user.management.view');
    const canEditUsers = can(auth, 'user.management.edit');

    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [selectedRoleFilters, setSelectedRoleFilters] = useState<number[]>(filters.role_ids ?? []);
    const [importOpen, setImportOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);

    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'skip',
    });

    const userForm = useForm<UserFormData>(initialUserForm);

    const roleOptions = useMemo<SelectOption[]>(
        () =>
            roles.map((role) => ({
                value: role.id,
                label: roleLabels[role.name] ?? role.name,
            })),
        [roles],
    );
    const permissionOptions = useMemo<SelectOption[]>(
        () =>
            permissions.map((permission) => ({
                value: permission.id,
                label: permission.name,
            })),
        [permissions],
    );

    useEffect(() => {
        setSelectedRoleFilters(filters.role_ids ?? []);
    }, [filters.role_ids]);

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 300);
        return () => clearTimeout(timer);
    }, [search]);

    function visitWithFilters(params: Partial<Props['filters']> = {}) {
        router.get(
            '/admin/users',
            {
                search: debouncedSearch || undefined,
                role_ids: selectedRoleFilters.length > 0 ? selectedRoleFilters : undefined,
                status: filters.status,
                has_official_photo: filters.has_official_photo,
                import_uploader_id: filters.import_uploader_id,
                role_name: isTeacherMode ? 'guru' : undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    useEffect(() => {
        if (
            debouncedSearch !== (filters.search ?? '') ||
            JSON.stringify(selectedRoleFilters) !== JSON.stringify(filters.role_ids ?? [])
        ) {
            visitWithFilters();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedSearch, selectedRoleFilters]);

    function handleStatusFilter(value: string) {
        visitWithFilters({ status: value === 'all' ? undefined : value });
    }

    function handleResetPassword(user: UserRow) {
        if (!window.confirm(`Reset password user "${user.name}" ke default?`)) return;
        router.post(`/admin/users/${user.id}/reset-password`, undefined, {
            onSuccess: () => toast.success('Password berhasil direset'),
            onError: () => toast.error('Gagal reset password'),
        });
    }

    function handleToggleActive(user: UserRow) {
        const action = user.is_active ? 'nonaktifkan' : 'aktifkan';
        if (!window.confirm(`${action === 'nonaktifkan' ? 'Nonaktifkan' : 'Aktifkan'} user "${user.name}"?`)) return;
        router.post(`/admin/users/${user.id}/toggle-active`, undefined, {
            onSuccess: () => toast.success('Status user diperbarui'),
            onError: () => toast.error('Gagal memperbarui status user'),
        });
    }

    function openTeacherMode() {
        router.get('/admin/users', { role_name: 'guru' }, { preserveScroll: true });
    }

    function openAllMode() {
        router.get('/admin/users', {}, { preserveScroll: true });
    }

    function handleImportSubmit(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/teachers-import', {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Import guru dimulai');
            },
            onError: () => toast.error('Gagal import guru'),
        });
    }

    function handleHistoryFilter(value: string) {
        router.get(
            '/admin/users',
            {
                search: debouncedSearch || undefined,
                role_ids: selectedRoleFilters.length > 0 ? selectedRoleFilters : undefined,
                status: filters.status,
                role_name: 'guru',
                import_uploader_id: value === 'all' ? undefined : value,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleRetry(runId: number) {
        router.post(`/admin/teachers-import-runs/${runId}/retry`, undefined, {
            onSuccess: () => toast.success('Retry import dijalankan'),
            onError: () => toast.error('Gagal retry import'),
        });
    }

    const hasRunningImport = useMemo(
        () => importRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [importRuns],
    );

    useEffect(() => {
        if (!isTeacherMode || !hasRunningImport) return;
        const timer = window.setInterval(() => router.reload({ only: ['importRuns'] }), 7000);
        return () => window.clearInterval(timer);
    }, [isTeacherMode, hasRunningImport]);

    function getProgressPercent(run: ImportRun): number {
        if (!run.total_rows || run.total_rows <= 0) return 0;
        return Math.min(100, Math.round((run.processed_rows / run.total_rows) * 100));
    }

    function getEtaLabel(run: ImportRun): string {
        if (
            run.status !== 'processing' ||
            !run.started_at ||
            run.processed_rows <= 0 ||
            run.total_rows <= run.processed_rows
        ) {
            return '-';
        }
        const startedAt = new Date(run.started_at).getTime();
        if (Number.isNaN(startedAt)) return '-';
        const elapsedSeconds = Math.max(1, Math.floor((Date.now() - startedAt) / 1000));
        const secPerRow = elapsedSeconds / run.processed_rows;
        const remainingRows = Math.max(0, run.total_rows - run.processed_rows);
        const etaSeconds = Math.ceil(secPerRow * remainingRows);
        return `${Math.floor(etaSeconds / 60)}m ${etaSeconds % 60}s`;
    }

    function formatDateTime(value: string): string {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function openCreateModal() {
        setCreateOpen(true);
        setEditingUser(null);
        userForm.reset();
        userForm.setData(initialUserForm);
        userForm.clearErrors();
    }

    function openEditModal(user: UserRow) {
        setEditingUser(user);
        setCreateOpen(false);
        userForm.clearErrors();
        userForm.setData({
            name: user.name,
            username: user.username ?? '',
            email: user.email,
            password: '',
            role_ids: (user.roles ?? []).map((role) => Number(role.id)),
            permission_ids: (user.permissions ?? []).map((permission) => Number(permission.id)),
            is_active: user.is_active,
        });
    }

    function closeUserModal() {
        setCreateOpen(false);
        setEditingUser(null);
        userForm.clearErrors();
        userForm.reset();
    }

    function submitCreateUser(e: React.FormEvent) {
        e.preventDefault();
        userForm.post('/admin/users', {
            onSuccess: () => {
                closeUserModal();
                toast.success('User berhasil ditambahkan');
            },
            onError: () => toast.error('Gagal menambah user'),
        });
    }

    function submitEditUser(e: React.FormEvent) {
        if (!editingUser) return;
        e.preventDefault();
        userForm.put(`/admin/users/${editingUser.id}`, {
            onSuccess: () => {
                closeUserModal();
                toast.success('User berhasil diperbarui');
            },
            onError: () => toast.error('Gagal memperbarui user'),
        });
    }

    const activeUsers = useMemo(() => users.data.filter((user) => user.is_active).length, [users.data]);
    const inactiveUsers = Math.max(0, users.data.length - activeUsers);
    const withoutOfficialPhoto = useMemo(
        () => users.data.filter((user) => !user.has_official_photo).length,
        [users.data],
    );
    const guruRows = useMemo(
        () => users.data.filter((user) => (user.roles ?? []).some((role) => role.name === 'guru')).length,
        [users.data],
    );

    const activeFilterCount = useMemo(() => {
        let total = 0;
        if (debouncedSearch.trim().length > 0) total += 1;
        if (selectedRoleFilters.length > 0) total += 1;
        if (filters.status) total += 1;
        if (filters.has_official_photo && filters.has_official_photo !== 'all') total += 1;
        return total;
    }, [debouncedSearch, selectedRoleFilters.length, filters.status, filters.has_official_photo]);

    function handleOfficialPhotoFilter(value: string) {
        visitWithFilters({ has_official_photo: value === 'all' ? undefined : value });
    }

    function handleUploadOfficialPhoto(userId: number, file: File | null) {
        if (!file) return;
        router.post(
            `/admin/users/${userId}/official-photo`,
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => toast.success('Foto resmi berhasil diupload'),
                onError: () => toast.error('Gagal upload foto resmi'),
            },
        );
    }

    function resetFilters() {
        setSearch('');
        setDebouncedSearch('');
        setSelectedRoleFilters([]);
        router.get(
            '/admin/users',
            { role_name: isTeacherMode ? 'guru' : undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    const selectedRoleOptions = roleOptions.filter((option) =>
        selectedRoleFilters.includes(Number(option.value)),
    );
    const selectedFormRoleOptions = roleOptions.filter((option) =>
        userForm.data.role_ids.includes(Number(option.value)),
    );
    const selectedFormPermissionOptions = permissionOptions.filter((option) =>
        userForm.data.permission_ids.includes(Number(option.value)),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen User" />
            <div>
                <CrudPageHeader
                    title={isTeacherMode ? 'Data Guru' : 'Manajemen User'}
                    description="Kelola akun pengguna dan akses role sistem."
                />

                {!canManageUsers ? (
                    <CrudCard>
                        <CrudEmptyState
                            title="Akses dibatasi"
                            description="Anda tidak memiliki permission untuk mengelola data user."
                        />
                    </CrudCard>
                ) : null}

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total User', value: users.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'active', label: 'Akun Aktif', value: activeUsers, icon: <ShieldCheck size={18} />, tone: 'green' },
                        { key: 'inactive', label: 'Akun Nonaktif', value: inactiveUsers, icon: <Power size={18} />, tone: 'amber' },
                        { key: 'no-photo', label: 'Belum Foto Resmi (halaman)', value: withoutOfficialPhoto, icon: <UserCog size={18} />, tone: 'amber' },
                        { key: 'guru', label: 'Role Guru (halaman)', value: guruRows, icon: <UserCog size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                {canManageUsers ? (
                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input
                                    placeholder="Cari nama, username, email..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <div style={{ minWidth: 250 }}>
                                <AppMultiSelect
                                    placeholder="Filter role..."
                                    options={roleOptions}
                                    value={selectedRoleOptions}
                                    onChange={(next) => setSelectedRoleFilters((next ?? []).map((it) => Number(it.value)))}
                                />
                            </div>
                            <select
                                className="mcr-filter-select"
                                value={filters.status ?? 'all'}
                                onChange={(e) => handleStatusFilter(e.target.value)}
                            >
                                <option value="all">Semua Status</option>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                            <select
                                className="mcr-filter-select"
                                value={filters.has_official_photo ?? 'all'}
                                onChange={(e) => handleOfficialPhotoFilter(e.target.value)}
                            >
                                <option value="all">Semua Foto Resmi</option>
                                <option value="1">Sudah Foto Resmi</option>
                                <option value="0">Belum Foto Resmi</option>
                            </select>
                            {activeFilterCount > 0 ? (
                                <button type="button" className="mcr-btn ghost" onClick={resetFilters}>
                                    <RotateCcw size={14} />
                                    Reset ({activeFilterCount})
                                </button>
                            ) : null}
                        </>
                    }
                    right={
                        <>
                            {isTeacherMode ? (
                                <button type="button" className="mcr-btn secondary" onClick={openAllMode}>
                                    Mode Semua User
                                </button>
                            ) : (
                                <button type="button" className="mcr-btn secondary" onClick={openTeacherMode}>
                                    Mode Data Guru
                                </button>
                            )}
                            {isTeacherMode ? (
                                <>
                                    <button
                                        type="button"
                                        className="mcr-btn secondary"
                                        onClick={() => openDownload(`/admin/teachers-export?format=xlsx&search=${encodeURIComponent(debouncedSearch)}&status=${filters.status ?? ''}`)}
                                    >
                                        <Download size={14} />
                                        XLSX
                                    </button>
                                    <button
                                        type="button"
                                        className="mcr-btn secondary"
                                        onClick={() => openDownload(`/admin/teachers-export?format=csv&search=${encodeURIComponent(debouncedSearch)}&status=${filters.status ?? ''}`)}
                                    >
                                        <Download size={14} />
                                        CSV
                                    </button>
                                    <button type="button" className="mcr-btn secondary" onClick={() => setImportOpen(true)}>
                                        <FileUp size={14} />
                                        Import
                                    </button>
                                </>
                            ) : null}
                            {canEditUsers ? (
                                <button type="button" className="mcr-btn primary" onClick={openCreateModal}>
                                    <Plus size={14} />
                                    Tambah User
                                </button>
                            ) : null}
                        </>
                    }
                />
                ) : null}

                {canManageUsers ? (
                <CrudTableShell>
                    <table className="mcr-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Foto Resmi</th>
                                <th style={{ textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7}>
                                        <CrudEmptyState
                                            title="Tidak ada data user"
                                            description="Coba ubah filter atau tambahkan user baru."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr key={user.id}>
                                        <td>
                                            <div className="mcr-student-cell">
                                                <span className="mcr-avatar">
                                                    {user.name.split(' ').slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase()}
                                                </span>
                                                <div>
                                                    <div className="name">{user.name}</div>
                                                    <div className="sub">ID #{user.id}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{user.username ?? '-'}</td>
                                        <td>{user.email}</td>
                                        <td>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                {(user.roles ?? []).length > 0 ? (
                                                    user.roles?.map((role) => (
                                                        <span key={`${user.id}-${role.id}`} className="mcr-dot-badge alumni">
                                                            {roleLabels[role.name] ?? role.name}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="mcr-dot-badge keluar">Tanpa Role</span>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            <span className={`mcr-dot-badge ${user.is_active ? 'active' : 'keluar'}`}>
                                                {user.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td>
                                            <span className={`mcr-dot-badge ${user.has_official_photo ? 'active' : 'keluar'}`}>
                                                {user.has_official_photo ? 'Sudah' : 'Belum'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="mcr-action-group">
                                                {canEditUsers ? (
                                                    <>
                                                        <button type="button" className="mcr-icon-action" title="Reset Password" onClick={() => handleResetPassword(user)}>
                                                            <RotateCcw size={13} />
                                                        </button>
                                                        <button type="button" className={`mcr-icon-action ${user.is_active ? 'danger' : ''}`} title={user.is_active ? 'Nonaktifkan' : 'Aktifkan'} onClick={() => handleToggleActive(user)}>
                                                            <Power size={13} />
                                                        </button>
                                                        <button type="button" className="mcr-icon-action" title="Edit" onClick={() => openEditModal(user)}>
                                                            <Pencil size={13} />
                                                        </button>
                                                    </>
                                                ) : null}
                                                {canManageUsers ? (
                                                    <Link href={`/admin/users/${user.id}`} className="mcr-icon-action" title="Detail">
                                                        <Eye size={13} />
                                                    </Link>
                                                ) : null}
                                                {canEditUsers ? (
                                                    <label className="mcr-icon-action" title="Upload Foto Resmi">
                                                        <FileUp size={13} />
                                                        <input
                                                            type="file"
                                                            accept="image/*"
                                                            style={{ display: 'none' }}
                                                            onChange={(e) => handleUploadOfficialPhoto(user.id, e.target.files?.[0] ?? null)}
                                                        />
                                                    </label>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CrudTableShell>
                ) : null}

                {canManageUsers ? <CrudPagination links={users.links} /> : null}

                {isTeacherMode && canManageUsers ? (
                    <CrudCard
                        title="Riwayat Import Guru"
                        subtitle="Pantau proses import guru dari CSV/XLSX."
                        right={hasRunningImport ? <span className="mcr-dot-badge active">Memproses...</span> : undefined}
                    >
                        <div style={{ marginBottom: 10 }}>
                            <select
                                className="mcr-filter-select"
                                value={filters.import_uploader_id ?? 'all'}
                                onChange={(e) => handleHistoryFilter(e.target.value)}
                            >
                                <option value="all">Semua Uploader</option>
                                {importUploaders.map((uploader) => (
                                    <option key={uploader.id} value={String(uploader.id)}>
                                        {uploader.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {importRuns.length === 0 ? (
                            <CrudEmptyState title="Belum ada riwayat import" description="Jalankan import guru untuk melihat histori di sini." />
                        ) : (
                            importRuns.map((run) => (
                                <div key={run.id} className="mcr-run-item">
                                    <div className="mcr-run-top">
                                        <div>
                                            <strong>{run.file_name}</strong>
                                            <div className="mcr-run-meta">{formatDateTime(run.created_at)} • Strategi {run.strategy.toUpperCase()}</div>
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
                                                <button type="button" className="mcr-btn secondary" onClick={() => handleRetry(run.id)}>
                                                    <RotateCcw size={14} />
                                                    Retry
                                                </button>
                                            ) : null}
                                        </div>
                                    </div>
                                    {(run.status === 'processing' || run.status === 'queued') && (
                                        <div style={{ marginTop: 8 }}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11, color: 'var(--mhs-text-3)', marginBottom: 4 }}>
                                                <span>{run.processed_rows}/{run.total_rows || '-'}</span>
                                                <span>{getProgressPercent(run)}% • ETA {getEtaLabel(run)}</span>
                                            </div>
                                            <div className="mcr-mini-progress" style={{ width: '100%' }}>
                                                <span style={{ width: `${getProgressPercent(run)}%` }} />
                                            </div>
                                        </div>
                                    )}
                                    <div className="mcr-run-stats">
                                        <span>C:{run.created_count}</span>
                                        <span>U:{run.updated_count}</span>
                                        <span>S:{run.skipped_count}</span>
                                        <span>F:{run.failed_count}</span>
                                    </div>
                                </div>
                            ))
                        )}
                    </CrudCard>
                ) : null}
            </div>

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Import Data Guru"
                subtitle="Unggah file CSV/XLSX lalu pilih strategi duplikat email."
            >
                <form onSubmit={handleImportSubmit}>
                    <div className="mcr-form-grid">
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
                            <label htmlFor="teacher-import-strategy">Strategi Duplikat (Email)</label>
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
                        <button type="button" className="mcr-btn ghost" onClick={() => setImportOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={importForm.processing}>
                            {importForm.processing ? 'Memproses...' : 'Proses Import'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal
                open={createOpen || editingUser !== null}
                onClose={closeUserModal}
                title={editingUser ? 'Edit User' : 'Tambah User'}
                subtitle="Kelola data akun dan role user (multi role)."
            >
                <form onSubmit={editingUser ? submitEditUser : submitCreateUser}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="user-name">Nama</label>
                            <input id="user-name" className="mcr-input" value={userForm.data.name} onChange={(e) => userForm.setData('name', e.target.value)} />
                            <InputError message={userForm.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="user-username">Username (opsional)</label>
                            <input id="user-username" className="mcr-input" value={userForm.data.username} onChange={(e) => userForm.setData('username', e.target.value)} />
                            <InputError message={userForm.errors.username} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="user-email">Email</label>
                            <input id="user-email" type="email" className="mcr-input" value={userForm.data.email} onChange={(e) => userForm.setData('email', e.target.value)} />
                            <InputError message={userForm.errors.email} />
                        </div>
                        {!editingUser ? (
                            <div className="mcr-form-group">
                                <label htmlFor="user-password">Password</label>
                                <input id="user-password" type="password" className="mcr-input" value={userForm.data.password} onChange={(e) => userForm.setData('password', e.target.value)} />
                                <InputError message={userForm.errors.password} />
                            </div>
                        ) : null}
                        <div className="mcr-form-group full">
                            <label>Role (bisa lebih dari satu)</label>
                            <AppMultiSelect
                                placeholder="Pilih role..."
                                options={roleOptions}
                                value={selectedFormRoleOptions}
                                onChange={(next) => userForm.setData('role_ids', (next ?? []).map((it) => Number(it.value)))}
                            />
                            <InputError message={userForm.errors.role_ids} />
                        </div>
                        <div className="mcr-form-group full">
                            <label>Direct Permission (opsional)</label>
                            <AppMultiSelect
                                placeholder="Pilih permission..."
                                options={permissionOptions}
                                value={selectedFormPermissionOptions}
                                onChange={(next) => userForm.setData('permission_ids', (next ?? []).map((it) => Number(it.value)))}
                            />
                            <InputError message={userForm.errors.permission_ids} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="user-active">Status Akun</label>
                            <select id="user-active" className="mcr-form-select" value={userForm.data.is_active ? '1' : '0'} onChange={(e) => userForm.setData('is_active', e.target.value === '1')}>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                            <InputError message={userForm.errors.is_active} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={closeUserModal}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={userForm.processing}>
                            {userForm.processing ? 'Menyimpan...' : editingUser ? 'Simpan Perubahan' : 'Tambah User'}
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
