import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    Eye,
    FileUp,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    UserPlus,
    UserRoundCheck,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppSelect,
    CrudBulkActionBar,
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
import type { SelectOption } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    DiniyahClass,
    ImportRun,
    PaginatedData,
    Student,
    User,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Data Santri', href: '/admin/students' },
];

type Props = {
    students: PaginatedData<Student>;
    classes: Pick<DiniyahClass, 'id' | 'name' | 'grade_level_id'>[];
    filters: {
        search?: string;
        status?: string;
        class_id?: string;
        import_uploader_id?: string;
    };
    importRuns: ImportRun[];
    importUploaders: Pick<User, 'id' | 'name'>[];
};

const statusMap: Record<
    string,
    { label: string; className: 'active' | 'alumni' | 'keluar' | 'wafat' }
> = {
    active: { label: 'Aktif', className: 'active' },
    alumni: { label: 'Alumni', className: 'alumni' },
    keluar: { label: 'Keluar', className: 'keluar' },
    wafat: { label: 'Wafat', className: 'wafat' },
};

export default function StudentIndex({
    students,
    classes,
    filters,
    importRuns,
    importUploaders,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [importOpen, setImportOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [studentToDelete, setStudentToDelete] = useState<Student | null>(null);
    const [selectedRows, setSelectedRows] = useState<number[]>([]);

    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'update',
    });

    const createForm = useForm({
        user_id: '',
        nis: '',
        full_name: '',
        admission_year: String(new Date().getFullYear()),
    });
    const [existingUserOptions, setExistingUserOptions] = useState<SelectOption[]>([]);
    const [isLoadingExistingUsers, setIsLoadingExistingUsers] = useState(false);
    const selectedExistingUserOption =
        existingUserOptions.find((item) => item.value === createForm.data.user_id) ?? null;

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(search);
        }, 300);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        if (debouncedSearch !== (filters.search ?? '')) {
            router.get(
                '/admin/students',
                {
                    search: debouncedSearch || undefined,
                    status: filters.status,
                    class_id: filters.class_id,
                },
                { preserveState: true, preserveScroll: true },
            );
        }
    }, [debouncedSearch, filters.class_id, filters.search, filters.status]);

    function handleFilter(key: string, value: string | undefined) {
        router.get(
            '/admin/students',
            {
                ...filters,
                search: debouncedSearch,
                [key]: value === 'all' ? undefined : value,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleDelete() {
        if (!studentToDelete) return;
        router.delete(`/admin/students/${studentToDelete.id}`, {
            onSuccess: () => {
                toast.success('Santri berhasil dihapus');
                setDeleteDialogOpen(false);
                setStudentToDelete(null);
                setSelectedRows((prev) => prev.filter((id) => id !== studentToDelete.id));
            },
            onError: () => {
                toast.error('Gagal menghapus santri');
            },
        });
    }

    function handleCreateSubmit(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/students', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Santri berhasil ditambahkan');
                setCreateOpen(false);
                createForm.reset();
            },
            onError: () => {
                toast.error('Gagal menambah santri');
            },
        });
    }

    async function loadEligibleUsers(searchTerm = '') {
        setIsLoadingExistingUsers(true);
        try {
            const url = new URL('/admin/students/eligible-users', window.location.origin);
            if (searchTerm.trim() !== '') {
                url.searchParams.set('search', searchTerm.trim());
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            const options: SelectOption[] = (payload?.data ?? []).map(
                (user: { id: number; name: string; email: string }) => ({
                    value: String(user.id),
                    label: `${user.name} (${user.email})`,
                }),
            );
            setExistingUserOptions(options);
        } catch {
            setExistingUserOptions([]);
        } finally {
            setIsLoadingExistingUsers(false);
        }
    }

    function handleImportSubmit(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/students-import', {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Import data dimulai, refresh halaman untuk melihat progres');
            },
            onError: () => {
                toast.error('Gagal mengimport data');
            },
        });
    }

    function handleHistoryFilter(value: string) {
        router.get(
            '/admin/students',
            {
                ...filters,
                search: debouncedSearch,
                import_uploader_id: value === 'all' ? undefined : value,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleRetry(runId: number) {
        router.post(`/admin/students-import-runs/${runId}/retry`, undefined, {
            onSuccess: () => toast.success('Proses import diulang'),
            onError: () => toast.error('Gagal mengulang import'),
        });
    }

    function resetFilters() {
        setSearch('');
        setDebouncedSearch('');
        setSelectedRows([]);
        router.get('/admin/students', {}, { preserveState: true, preserveScroll: true });
    }

    const hasRunningImport = useMemo(
        () => importRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [importRuns],
    );

    useEffect(() => {
        if (!hasRunningImport) return;
        const timer = window.setInterval(() => {
            router.reload({ only: ['importRuns'] });
        }, 7000);
        return () => window.clearInterval(timer);
    }, [hasRunningImport]);

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
        const minutes = Math.floor(etaSeconds / 60);
        const seconds = etaSeconds % 60;
        return `${minutes}m ${seconds}s`;
    }

    const activeFilterCount = useMemo(() => {
        let total = 0;
        if (debouncedSearch.trim().length > 0) total += 1;
        if (filters.status) total += 1;
        if (filters.class_id) total += 1;
        return total;
    }, [filters.class_id, filters.status, debouncedSearch]);

    const completedImports = useMemo(
        () => importRuns.filter((run) => run.status === 'completed').length,
        [importRuns],
    );

    const activeStudents = useMemo(
        () => students.data.filter((student) => student.status === 'active').length,
        [students.data],
    );

    const inactiveStudents = useMemo(
        () => students.data.filter((student) => student.status !== 'active').length,
        [students.data],
    );

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

    function toggleRow(id: number): void {
        setSelectedRows((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
        );
    }

    function toggleSelectAll(): void {
        if (students.data.length === 0) return;
        const allIds = students.data.map((student) => student.id);
        const isAllSelected = allIds.every((id) => selectedRows.includes(id));
        setSelectedRows(isAllSelected ? [] : allIds);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Santri" />
            <div>
                <CrudPageHeader
                    title="Data Santri"
                    description="Kelola seluruh data santri pesantren — tambah, edit, lihat, dan hapus."
                />

                <CrudStatStrip
                    items={[
                        {
                            key: 'total',
                            label: 'Total Santri',
                            value: students.total,
                            icon: <Users size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'active-page',
                            label: 'Santri Aktif',
                            value: activeStudents,
                            icon: <UserRoundCheck size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'imports',
                            label: 'Import Selesai',
                            value: completedImports,
                            icon: <CheckCircle2 size={18} />,
                            tone: 'amber',
                        },
                        {
                            key: 'inactive',
                            label: 'Status Non Aktif',
                            value: inactiveStudents,
                            icon: <Wallet size={18} />,
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
                                    placeholder="Cari nama, NIS, kelas..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <select
                                className="mcr-filter-select"
                                value={filters.class_id ?? 'all'}
                                onChange={(e) => handleFilter('class_id', e.target.value)}
                            >
                                <option value="all">Semua Kelas</option>
                                {classes.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="mcr-filter-select"
                                value={filters.status ?? 'all'}
                                onChange={(e) => handleFilter('status', e.target.value)}
                            >
                                <option value="all">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="alumni">Alumni</option>
                                <option value="keluar">Keluar</option>
                                <option value="wafat">Wafat</option>
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
                            <button
                                type="button"
                                className="mcr-btn secondary"
                                onClick={() =>
                                    openDownload(
                                        `/admin/students-export?format=xlsx&search=${encodeURIComponent(
                                            debouncedSearch,
                                        )}&status=${filters.status ?? ''}&class_id=${filters.class_id ?? ''}`,
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
                                Import CSV
                            </button>
                            <button
                                type="button"
                                className="mcr-btn primary"
                                onClick={() => setCreateOpen(true)}
                            >
                                <UserPlus size={14} />
                                Tambah Santri
                            </button>
                        </>
                    }
                />

                <CrudTableShell>
                    <table className="mcr-table">
                        <thead>
                            <tr>
                                <th style={{ width: 36 }}>
                                    <input
                                        type="checkbox"
                                        className="mcr-check"
                                        checked={
                                            students.data.length > 0 &&
                                            students.data.every((student) =>
                                                selectedRows.includes(student.id),
                                            )
                                        }
                                        onChange={toggleSelectAll}
                                    />
                                </th>
                                <th>Santri</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th>Akun</th>
                                <th style={{ textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7}>
                                        <CrudEmptyState
                                            title="Tidak ada data santri"
                                            description="Coba ubah filter atau tambah data santri baru."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                students.data.map((student) => {
                                    const status = statusMap[student.status];
                                    return (
                                        <tr key={student.id}>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    className="mcr-check"
                                                    checked={selectedRows.includes(student.id)}
                                                    onChange={() => toggleRow(student.id)}
                                                />
                                            </td>
                                            <td>
                                                <div className="mcr-student-cell">
                                                    <span className="mcr-avatar">
                                                        {student.full_name
                                                            .split(' ')
                                                            .slice(0, 2)
                                                            .map((part) => part[0] ?? '')
                                                            .join('')
                                                            .toUpperCase()}
                                                    </span>
                                                    <div>
                                                        <div className="name">{student.full_name}</div>
                                                        <div className="sub">
                                                            {student.gender === 'L'
                                                                ? 'Laki-laki'
                                                                : 'Perempuan'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{student.nis}</td>
                                            <td>{student.current_class?.name ?? '-'}</td>
                                            <td>
                                                <span
                                                    className={`mcr-dot-badge ${status?.className ?? 'alumni'}`}
                                                >
                                                    {status?.label ?? student.status}
                                                </span>
                                            </td>
                                            <td>
                                                <span
                                                    className={`mcr-dot-badge ${
                                                        student.user_id ? 'active' : 'keluar'
                                                    }`}
                                                >
                                                    {student.user_id ? 'Aktif' : 'Belum'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <Link
                                                        href={`/admin/students/${student.id}`}
                                                        className="mcr-icon-action"
                                                        title="Lihat"
                                                    >
                                                        <Eye size={13} />
                                                    </Link>
                                                    <Link
                                                        href={`/admin/students/${student.id}/edit`}
                                                        className="mcr-icon-action"
                                                        title="Edit"
                                                    >
                                                        <Pencil size={13} />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        className="mcr-icon-action danger"
                                                        title="Hapus"
                                                        onClick={() => {
                                                            setStudentToDelete(student);
                                                            setDeleteDialogOpen(true);
                                                        }}
                                                    >
                                                        <Trash2 size={13} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </CrudTableShell>

                <CrudPagination links={students.links} />

                <CrudBulkActionBar
                    visible={selectedRows.length > 0}
                    selectedCount={selectedRows.length}
                    onClear={() => setSelectedRows([])}
                >
                    <button type="button" className="mcr-btn secondary" onClick={() => setSelectedRows([])}>
                        Batalkan
                    </button>
                    <button
                        type="button"
                        className="mcr-btn secondary"
                        onClick={() =>
                            openDownload(
                                `/admin/students-export?format=csv&selected=${encodeURIComponent(
                                    selectedRows.join(','),
                                )}`,
                            )
                        }
                    >
                        <Download size={14} />
                        Export Terpilih
                    </button>
                </CrudBulkActionBar>

                <CrudCard
                    title="Riwayat Import"
                    subtitle="Pantau proses import data santri"
                    right={
                        hasRunningImport ? (
                            <span className="mcr-dot-badge active">Memproses...</span>
                        ) : undefined
                    }
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
                        <CrudEmptyState
                            title="Belum ada riwayat import"
                            description="Upload file CSV atau XLSX untuk memulai import data."
                        />
                    ) : (
                        importRuns.map((run) => (
                            <div key={run.id} className="mcr-run-item">
                                <div className="mcr-run-top">
                                    <div>
                                        <strong>{run.file_name}</strong>
                                        <div className="mcr-run-meta">
                                            {formatDateTime(run.created_at)} • Strategi:{' '}
                                            {run.strategy.toUpperCase()}
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <span
                                            className={`mcr-dot-badge ${
                                                run.status === 'completed'
                                                    ? 'active'
                                                    : run.status === 'failed'
                                                      ? 'wafat'
                                                      : 'keluar'
                                            }`}
                                        >
                                            {run.status}
                                        </span>
                                        {run.error_report_path ? (
                                            <a
                                                href={`/admin/students-import-errors/${run.uuid}`}
                                                className="mcr-btn secondary"
                                            >
                                                Error CSV
                                            </a>
                                        ) : null}
                                        {run.status === 'failed' ? (
                                            <button
                                                type="button"
                                                className="mcr-btn secondary"
                                                onClick={() => handleRetry(run.id)}
                                            >
                                                <RotateCcw size={14} />
                                                Retry
                                            </button>
                                        ) : null}
                                    </div>
                                </div>
                                {(run.status === 'processing' || run.status === 'queued') && (
                                    <div style={{ marginTop: 8 }}>
                                        <div
                                            style={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                fontSize: 11,
                                                color: 'var(--mhs-text-3)',
                                                marginBottom: 4,
                                            }}
                                        >
                                            <span>
                                                {run.processed_rows} / {run.total_rows || '?'} baris
                                            </span>
                                            <span>
                                                {getProgressPercent(run)}% • ETA {getEtaLabel(run)}
                                            </span>
                                        </div>
                                        <div className="mcr-mini-progress" style={{ width: '100%' }}>
                                            <span style={{ width: `${getProgressPercent(run)}%` }} />
                                        </div>
                                    </div>
                                )}
                                <div className="mcr-run-stats">
                                    <span>Created: {run.created_count}</span>
                                    <span>Updated: {run.updated_count}</span>
                                    <span>Skipped: {run.skipped_count}</span>
                                    <span>Failed: {run.failed_count}</span>
                                </div>
                            </div>
                        ))
                    )}
                </CrudCard>
            </div>

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Import Data Santri"
                subtitle="Unggah file CSV/XLSX lalu pilih strategi duplikat."
            >
                <form onSubmit={handleImportSubmit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="import-file">File Import</label>
                            <input
                                id="import-file"
                                className="mcr-input"
                                type="file"
                                accept=".xlsx,.csv"
                                onChange={(e) =>
                                    importForm.setData('file', e.target.files?.[0] ?? null)
                                }
                            />
                            <InputError message={importForm.errors.file} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="import-strategy">Strategi Duplikat (NIS)</label>
                            <select
                                id="import-strategy"
                                className="mcr-form-select"
                                value={importForm.data.strategy}
                                onChange={(e) =>
                                    importForm.setData('strategy', e.target.value as 'skip' | 'update')
                                }
                            >
                                <option value="update">Perbarui data yang sudah ada</option>
                                <option value="skip">Lewati data yang sudah ada</option>
                            </select>
                            <InputError message={importForm.errors.strategy} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button
                            type="button"
                            className="mcr-btn secondary"
                            onClick={() => openDownload('/admin/students-template?format=xlsx')}
                        >
                            <Download size={14} />
                            Download Template
                        </button>
                        <button
                            type="button"
                            className="mcr-btn ghost"
                            onClick={() => setImportOpen(false)}
                        >
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={importForm.processing}>
                            {importForm.processing ? 'Memproses...' : 'Proses Import'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                title="Tambah Santri Baru"
                subtitle="Isi data inti santri di bawah ini"
                wide
            >
                <form onSubmit={handleCreateSubmit}>
                    <div className="mcr-section-title">Akun Existing</div>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="existing-user">Pilih User Existing (Opsional)</label>
                            <AppSelect
                                inputId="existing-user"
                                placeholder="Cari user..."
                                options={existingUserOptions}
                                isLoading={isLoadingExistingUsers}
                                value={selectedExistingUserOption}
                                onChange={(option) =>
                                    createForm.setData('user_id', String(option?.value ?? ''))
                                }
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
                            <InputError message={createForm.errors.user_id} />
                        </div>
                    </div>

                    <div className="mcr-section-title">Data Pribadi</div>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="full_name">Nama Lengkap *</label>
                            <input
                                id="full_name"
                                className="mcr-input"
                                value={createForm.data.full_name}
                                onChange={(e) => createForm.setData('full_name', e.target.value)}
                                placeholder="Masukkan nama santri"
                                required
                            />
                            <InputError message={createForm.errors.full_name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="nis">NIS *</label>
                            <input
                                id="nis"
                                className="mcr-input"
                                value={createForm.data.nis}
                                onChange={(e) => createForm.setData('nis', e.target.value)}
                                placeholder="Masukkan NIS"
                                required
                            />
                            <InputError message={createForm.errors.nis} />
                        </div>
                    </div>

                    <div className="mcr-section-title">Data Akademik</div>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="admission_year">Tahun Masuk *</label>
                            <input
                                id="admission_year"
                                className="mcr-input"
                                type="number"
                                min={2000}
                                max={2099}
                                value={createForm.data.admission_year}
                                onChange={(e) => createForm.setData('admission_year', e.target.value)}
                                required
                            />
                            <InputError message={createForm.errors.admission_year} />
                        </div>
                        <div className="mcr-form-group">
                            <label>Kelas Awal</label>
                            <select className="mcr-form-select" defaultValue="">
                                <option value="">-- Pilih kelas --</option>
                                {classes.map((kelas) => (
                                    <option key={kelas.id} value={String(kelas.id)}>
                                        {kelas.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="mcr-section-title">Wali / Orang Tua</div>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label>Nama Wali</label>
                            <input className="mcr-input" placeholder="(Opsional)" />
                        </div>
                        <div className="mcr-form-group">
                            <label>No. Telepon Wali</label>
                            <input className="mcr-input" placeholder="08xx-xxxx-xxxx" />
                        </div>
                        <div className="mcr-form-group full">
                            <label>Alamat Wali</label>
                            <textarea className="mcr-textarea" placeholder="(Opsional)" />
                        </div>
                    </div>

                    <div style={{ marginTop: 14, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button
                            type="button"
                            className="mcr-btn ghost"
                            onClick={() => setCreateOpen(false)}
                        >
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={createForm.processing}>
                            <Plus size={14} />
                            {createForm.processing ? 'Menyimpan...' : 'Simpan Data'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteDialogOpen}
                onClose={() => setDeleteDialogOpen(false)}
                onConfirm={handleDelete}
                title="Konfirmasi Hapus"
                description={`Hapus data santri "${studentToDelete?.full_name ?? '-'}"?`}
                confirmLabel="Hapus Sekarang"
            />
        </AppLayout>
    );
}
