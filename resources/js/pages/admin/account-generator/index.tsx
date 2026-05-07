import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Copy, Eye, RefreshCw, ShieldCheck, UserPlus, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import {
    CrudBulkActionBar,
    CrudCard,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Guardian, ImportRun, PaginatedData, Student, User } from '@/types';

type GuardianWithoutAccount = Guardian & {
    student?: Pick<Student, 'id' | 'nis' | 'full_name'>;
    relationship?: string;
    student_id?: number;
};

type Props = {
    studentsWithoutAccount: PaginatedData<Pick<Student, 'id' | 'nis' | 'full_name'>>;
    guardiansWithoutAccount: PaginatedData<GuardianWithoutAccount>;
    bulkRuns: ImportRun[];
    bulkUploaders: Pick<User, 'id' | 'name'>[];
    runFilters: { run_uploader_id?: string; per_page?: string };
    perPageOptions: number[];
};

type GeneratedCredential = {
    label: string;
    secondaryLabel: string;
    username: string;
    password: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Generate Akun', href: '/admin/account-generator' },
];

export default function AccountGeneratorIndex({
    studentsWithoutAccount,
    guardiansWithoutAccount,
    bulkRuns,
    bulkUploaders,
    runFilters,
    perPageOptions,
}: Props) {
    const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
    const [selectedGuardians, setSelectedGuardians] = useState<number[]>([]);
    const [includeWaliOnStudentRun, setIncludeWaliOnStudentRun] = useState(true);
    const [resultRunId, setResultRunId] = useState<number | null>(null);

    const studentForm = useForm<{ student_ids: number[]; include_wali_accounts: boolean }>({
        student_ids: [],
        include_wali_accounts: true,
    });
    const guardianForm = useForm<{ guardian_ids: number[] }>({ guardian_ids: [] });

    const hasRunningJobs = useMemo(
        () => bulkRuns.some((run) => run.status === 'queued' || run.status === 'processing'),
        [bulkRuns],
    );
    const selectedResultRun = useMemo(() => bulkRuns.find((run) => run.id === resultRunId) ?? null, [bulkRuns, resultRunId]);

    function normalizeGeneratedCredentials(run: ImportRun): GeneratedCredential[] {
        const payload = run.result_payload;
        if (!payload || typeof payload !== 'object') return [];

        const accounts = Array.isArray(payload.generated_accounts) ? payload.generated_accounts : [];
        const waliAccounts = Array.isArray(payload.generated_wali_accounts) ? payload.generated_wali_accounts : [];

        const studentRows = accounts
            .map((item) => {
                if (!item || typeof item !== 'object') return null;
                const data = item as Record<string, unknown>;
                const username = typeof data.username === 'string' ? data.username : '';
                const password = typeof data.password === 'string' ? data.password : '';
                if (!username || !password) return null;
                return {
                    label: typeof data.name === 'string' ? data.name : 'Santri',
                    secondaryLabel: typeof data.nis === 'string' ? `NIS: ${data.nis}` : 'Akun santri',
                    username,
                    password,
                } satisfies GeneratedCredential;
            })
            .filter((row): row is GeneratedCredential => row !== null);

        const waliRows = waliAccounts
            .map((item) => {
                if (!item || typeof item !== 'object') return null;
                const data = item as Record<string, unknown>;
                const username = typeof data.username === 'string' ? data.username : '';
                const password = typeof data.password === 'string' ? data.password : '';
                if (!username || !password) return null;
                return {
                    label: typeof data.guardian_name === 'string' ? data.guardian_name : 'Wali',
                    secondaryLabel: typeof data.student_nis === 'string' ? `NIS Santri: ${data.student_nis}` : 'Akun wali',
                    username,
                    password,
                } satisfies GeneratedCredential;
            })
            .filter((row): row is GeneratedCredential => row !== null);

        return [...studentRows, ...waliRows];
    }

    const selectedResultRows = useMemo(
        () => (selectedResultRun ? normalizeGeneratedCredentials(selectedResultRun) : []),
        [selectedResultRun],
    );
    const shouldAutoRefreshRuns = hasRunningJobs || resultRunId !== null;

    useEffect(() => {
        if (!shouldAutoRefreshRuns) return;

        const interval = window.setInterval(() => {
            router.reload({
                only: ['bulkRuns'],
            });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [shouldAutoRefreshRuns]);

    async function copyCredential(row: GeneratedCredential) {
        try {
            await navigator.clipboard.writeText(`Username: ${row.username}\nPassword: ${row.password}`);
            toast.success(`Kredensial "${row.label}" berhasil disalin`);
        } catch {
            toast.error('Gagal menyalin kredensial');
        }
    }

    async function copyAllCredentials(rows: GeneratedCredential[]) {
        if (rows.length === 0) return;
        const text = rows
            .map((row) => `${row.label} (${row.secondaryLabel})\nUsername: ${row.username}\nPassword: ${row.password}`)
            .join('\n\n');
        try {
            await navigator.clipboard.writeText(text);
            toast.success(`Berhasil salin ${rows.length} kredensial`);
        } catch {
            toast.error('Gagal menyalin seluruh kredensial');
        }
    }

    function setPerPage(value: string) {
        router.get('/admin/account-generator', { ...runFilters, per_page: value }, { preserveState: true, preserveScroll: true });
    }

    function setRunUploader(value: string) {
        router.get('/admin/account-generator', { ...runFilters, run_uploader_id: value === 'all' ? undefined : value }, { preserveState: true, preserveScroll: true });
    }

    function toggleStudent(id: number) {
        setSelectedStudents((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function toggleGuardian(id: number) {
        setSelectedGuardians((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function toggleAllStudents() {
        const allIds = studentsWithoutAccount.data.map((row) => row.id);
        const allChecked = allIds.length > 0 && allIds.every((id) => selectedStudents.includes(id));
        setSelectedStudents(allChecked ? [] : allIds);
    }

    function toggleAllGuardians() {
        const allIds = guardiansWithoutAccount.data.map((row) => row.id);
        const allChecked = allIds.length > 0 && allIds.every((id) => selectedGuardians.includes(id));
        setSelectedGuardians(allChecked ? [] : allIds);
    }

    function submitGenerateStudents() {
        if (selectedStudents.length === 0) {
            toast.error('Pilih minimal satu santri');
            return;
        }
        studentForm.setData({
            student_ids: selectedStudents,
            include_wali_accounts: includeWaliOnStudentRun,
        });
        studentForm.post('/admin/account-generator/students', {
            onSuccess: () => {
                setSelectedStudents([]);
                toast.success('Generate akun santri diproses di background');
            },
            onError: () => toast.error('Gagal menjalankan generate akun santri'),
        });
    }

    function submitGenerateGuardians() {
        if (selectedGuardians.length === 0) {
            toast.error('Pilih minimal satu wali');
            return;
        }
        guardianForm.setData({ guardian_ids: selectedGuardians });
        guardianForm.post('/admin/account-generator/guardians', {
            onSuccess: () => {
                setSelectedGuardians([]);
                toast.success('Generate akun wali diproses di background');
            },
            onError: () => toast.error('Gagal menjalankan generate akun wali'),
        });
    }

    function retryRun(run: ImportRun) {
        router.post(`/admin/account-generator/runs/${run.id}/retry`, undefined, {
            onSuccess: () => toast.success('Retry job diproses di background'),
            onError: () => toast.error('Gagal retry job'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Generate Akun" />
            <div>
                <CrudPageHeader
                    title="Generate Akun Santri & Wali"
                    description="Buat akun secara massal untuk data yang belum memiliki user login."
                />

                <CrudStatStrip
                    items={[
                        { key: 'students', label: 'Santri Belum Akun', value: studentsWithoutAccount.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'guardians', label: 'Wali Belum Akun', value: guardiansWithoutAccount.total, icon: <UserPlus size={18} />, tone: 'amber' },
                        { key: 'runs', label: 'Job Terakhir', value: bulkRuns.length, icon: <RefreshCw size={18} />, tone: 'purple' },
                        { key: 'active', label: 'Job Berjalan', value: hasRunningJobs ? 'Ya' : 'Tidak', icon: <CheckCircle2 size={18} />, tone: 'green' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select className="mcr-filter-select" value={runFilters.per_page ?? String(perPageOptions[0] ?? 25)} onChange={(e) => setPerPage(e.target.value)}>
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>{opt} / halaman</option>
                                ))}
                            </select>
                            <span className="mcr-table-meta">Pilih data lalu jalankan generate akun secara background job.</span>
                        </>
                    }
                />

                <CrudCard title="Santri Belum Memiliki Akun" subtitle="Pilih santri lalu generate akun santri.">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <input type="checkbox" className="mcr-check" checked={studentsWithoutAccount.data.length > 0 && studentsWithoutAccount.data.every((s) => selectedStudents.includes(s.id))} onChange={toggleAllStudents} />
                                    </th>
                                    <th>NIS</th>
                                    <th>Nama Santri</th>
                                </tr>
                            </thead>
                            <tbody>
                                {studentsWithoutAccount.data.length === 0 ? (
                                    <tr><td colSpan={3}><CrudEmptyState title="Tidak ada data" description="Semua santri sudah memiliki akun." /></td></tr>
                                ) : (
                                    studentsWithoutAccount.data.map((student) => (
                                        <tr key={student.id}>
                                            <td><input type="checkbox" className="mcr-check" checked={selectedStudents.includes(student.id)} onChange={() => toggleStudent(student.id)} /></td>
                                            <td>{student.nis}</td>
                                            <td>{student.full_name}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={studentsWithoutAccount.links} />
                    <CrudBulkActionBar visible={selectedStudents.length > 0} selectedCount={selectedStudents.length} onClear={() => setSelectedStudents([])}>
                        <label className="mcr-checkline" style={{ marginRight: 8 }}>
                            <input type="checkbox" checked={includeWaliOnStudentRun} onChange={(e) => setIncludeWaliOnStudentRun(e.target.checked)} />
                            <span>Sekaligus buat akun wali terkait</span>
                        </label>
                        <button type="button" className="mcr-btn primary" onClick={submitGenerateStudents} disabled={studentForm.processing}>
                            <ShieldCheck size={14} />
                            {studentForm.processing ? 'Memproses...' : 'Generate Akun Santri'}
                        </button>
                    </CrudBulkActionBar>
                </CrudCard>

                <CrudCard title="Wali Belum Memiliki Akun" subtitle="Pilih wali lalu generate akun wali.">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <input type="checkbox" className="mcr-check" checked={guardiansWithoutAccount.data.length > 0 && guardiansWithoutAccount.data.every((g) => selectedGuardians.includes(g.id))} onChange={toggleAllGuardians} />
                                    </th>
                                    <th>Nama Wali</th>
                                    <th>Santri Terkait</th>
                                    <th>Relasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {guardiansWithoutAccount.data.length === 0 ? (
                                    <tr><td colSpan={4}><CrudEmptyState title="Tidak ada data" description="Semua wali sudah memiliki akun." /></td></tr>
                                ) : (
                                    guardiansWithoutAccount.data.map((guardian) => (
                                        <tr key={guardian.id}>
                                            <td><input type="checkbox" className="mcr-check" checked={selectedGuardians.includes(guardian.id)} onChange={() => toggleGuardian(guardian.id)} /></td>
                                            <td>{guardian.full_name}</td>
                                            <td>{guardian.student?.full_name ?? '-'}</td>
                                            <td>{guardian.relationship ?? '-'}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={guardiansWithoutAccount.links} />
                    <CrudBulkActionBar visible={selectedGuardians.length > 0} selectedCount={selectedGuardians.length} onClear={() => setSelectedGuardians([])}>
                        <button type="button" className="mcr-btn primary" onClick={submitGenerateGuardians} disabled={guardianForm.processing}>
                            <ShieldCheck size={14} />
                            {guardianForm.processing ? 'Memproses...' : 'Generate Akun Wali'}
                        </button>
                    </CrudBulkActionBar>
                </CrudCard>

                <CrudCard
                    title="Riwayat Job Generate Akun"
                    subtitle="Pantau status job background untuk generate akun."
                    right={hasRunningJobs ? <span className="mcr-dot-badge active">Berjalan...</span> : undefined}
                >
                    <div style={{ marginBottom: 10 }}>
                        <select className="mcr-filter-select" value={runFilters.run_uploader_id ?? 'all'} onChange={(e) => setRunUploader(e.target.value)}>
                            <option value="all">Semua Uploader</option>
                            {bulkUploaders.map((u) => (
                                <option key={u.id} value={String(u.id)}>{u.name}</option>
                            ))}
                        </select>
                    </div>
                    {bulkRuns.length === 0 ? (
                        <CrudEmptyState title="Belum ada job" description="Job generate akun akan muncul setelah proses dijalankan." />
                    ) : (
                        bulkRuns.map((run) => (
                            <div key={run.id} className="mcr-run-item">
                                <div className="mcr-run-top">
                                    <div>
                                        <strong>{run.file_name}</strong>
                                        <div className="mcr-run-meta">{run.job_type ?? run.type} • {run.requestedBy?.name ?? 'Sistem'}</div>
                                    </div>
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <span className={`mcr-dot-badge ${run.status === 'completed' ? 'active' : run.status === 'failed' ? 'wafat' : 'keluar'}`}>{run.status}</span>
                                        {normalizeGeneratedCredentials(run).length > 0 ? (
                                            <button type="button" className="mcr-btn secondary" onClick={() => setResultRunId(run.id)}>
                                                <Eye size={14} />
                                                Lihat Hasil
                                            </button>
                                        ) : null}
                                        {run.status === 'failed' ? (
                                            <button type="button" className="mcr-btn secondary" onClick={() => retryRun(run)}>
                                                <RefreshCw size={14} />
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
                                    <span>
                                        Progress:{' '}
                                        {run.total_rows > 0
                                            ? `${Math.min(100, Math.round((run.processed_rows / run.total_rows) * 100))}% (${run.processed_rows}/${run.total_rows})`
                                            : '-'}
                                    </span>
                                </div>
                            </div>
                        ))
                    )}
                </CrudCard>

                <CrudModal
                    open={resultRunId !== null}
                    title="Hasil Generate Akun"
                    subtitle={selectedResultRun ? `${selectedResultRun.file_name} • ${selectedResultRows.length} kredensial` : undefined}
                    onClose={() => setResultRunId(null)}
                    wide
                    footer={
                        <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                            <button type="button" className="mcr-btn ghost" onClick={() => setResultRunId(null)}>
                                Tutup
                            </button>
                            <button
                                type="button"
                                className="mcr-btn primary"
                                onClick={() => copyAllCredentials(selectedResultRows)}
                                disabled={selectedResultRows.length === 0}
                            >
                                <Copy size={14} />
                                Salin Semua Kredensial
                            </button>
                        </div>
                    }
                >
                    {selectedResultRows.length === 0 ? (
                        <CrudEmptyState title="Belum ada hasil" description="Kredensial hanya tersedia untuk job generate akun yang sudah selesai." />
                    ) : (
                        <CrudTableShell>
                            <table className="mcr-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th style={{ width: 170 }}>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {selectedResultRows.map((row) => (
                                        <tr key={`${row.username}-${row.password}`}>
                                            <td>
                                                <div>{row.label}</div>
                                                <div className="mcr-table-meta">{row.secondaryLabel}</div>
                                            </td>
                                            <td>{row.username}</td>
                                            <td>{row.password}</td>
                                            <td>
                                                <button type="button" className="mcr-btn secondary" onClick={() => copyCredential(row)}>
                                                    <Copy size={14} />
                                                    Salin Kredensial
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CrudTableShell>
                    )}
                </CrudModal>
            </div>
        </AppLayout>
    );
}
