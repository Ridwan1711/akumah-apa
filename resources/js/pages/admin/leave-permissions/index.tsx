import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck2, Clock3, Plus, UserCheck, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    AppSelect,
    CrudCard,
    CrudConfirmModal,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
    type SelectOption,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, LeavePermission, PaginatedData, Student } from '@/types';

type Props = {
    permissions: PaginatedData<LeavePermission>;
    filters: { status?: string; search?: string };
    students: Pick<Student, 'id' | 'nis' | 'full_name'>[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Perizinan', href: '/admin/leave-permissions' },
];

const statusOptions: SelectOption[] = [
    { value: 'all', label: 'Semua status' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'rejected', label: 'Ditolak' },
];

export default function LeavePermissionIndex({ permissions, filters, students }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [rejectTarget, setRejectTarget] = useState<LeavePermission | null>(null);
    const [approveTarget, setApproveTarget] = useState<LeavePermission | null>(null);
    const [returnedTarget, setReturnedTarget] = useState<LeavePermission | null>(null);

    const createForm = useForm({
        student_id: '',
        reason: '',
        leave_date: '',
        return_date: '',
    });

    const rejectForm = useForm({
        rejection_reason: '',
    });

    const pendingCount = useMemo(
        () => permissions.data.filter((item) => item.status === 'pending').length,
        [permissions.data],
    );
    const approvedCount = useMemo(
        () => permissions.data.filter((item) => item.status === 'approved').length,
        [permissions.data],
    );
    const rejectedCount = useMemo(
        () => permissions.data.filter((item) => item.status === 'rejected').length,
        [permissions.data],
    );

    const studentOptions = useMemo<SelectOption[]>(
        () =>
            students.map((student) => ({
                value: String(student.id),
                label: `${student.full_name} (${student.nis})`,
            })),
        [students],
    );

    function visitWithFilters(next: Partial<Props['filters']>) {
        router.get(
            '/admin/leave-permissions',
            {
                ...filters,
                ...next,
                search: (next.search ?? filters.search ?? '').trim() || undefined,
                status:
                    (next.status ?? filters.status ?? 'all') === 'all'
                        ? undefined
                        : next.status ?? filters.status,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/leave-permissions', {
            onSuccess: () => {
                createForm.reset();
                setIsCreateOpen(false);
            },
        });
    }

    function approvePermission() {
        if (!approveTarget) return;
        router.post(`/admin/leave-permissions/${approveTarget.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => setApproveTarget(null),
        });
    }

    function rejectPermission() {
        if (!rejectTarget) return;
        rejectForm.post(`/admin/leave-permissions/${rejectTarget.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                setRejectTarget(null);
            },
        });
    }

    function markReturned() {
        if (!returnedTarget) return;
        router.post(`/admin/leave-permissions/${returnedTarget.id}/returned`, {}, {
            preserveScroll: true,
            onFinish: () => setReturnedTarget(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perizinan" />
            <div>
                <CrudPageHeader
                    title="Perizinan Santri"
                    description="Kelola pengajuan izin keluar, approval, dan status kembali."
                />

                <CrudStatStrip
                    items={[
                        { key: 'all', label: 'Total Data', value: permissions.total, icon: <CalendarCheck2 size={18} />, tone: 'blue' },
                        { key: 'pending', label: 'Pending', value: pendingCount, icon: <Clock3 size={18} />, tone: 'amber' },
                        { key: 'approved', label: 'Disetujui', value: approvedCount, icon: <UserCheck size={18} />, tone: 'green' },
                        { key: 'rejected', label: 'Ditolak', value: rejectedCount, icon: <XCircle size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            visitWithFilters({ search });
                                        }
                                    }}
                                    placeholder="Cari nama santri..."
                                />
                            </div>
                            <AppSelect
                                options={statusOptions}
                                value={statusOptions.find((option) => option.value === (filters.status ?? 'all'))}
                                onChange={(option) => visitWithFilters({ status: String(option?.value ?? 'all') })}
                                placeholder="Status"
                            />
                        </>
                    }
                    right={
                        <button
                            type="button"
                            className="mcr-btn primary"
                            onClick={() => setIsCreateOpen(true)}
                        >
                            <Plus size={14} />
                            Ajukan Izin
                        </button>
                    }
                />

                <CrudCard title="Daftar Pengajuan Izin">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Santri</th>
                                    <th>Tanggal Izin</th>
                                    <th>Tanggal Kembali</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {permissions.data.map((item) => (
                                    <tr key={item.id}>
                                        <td>
                                            <div style={{ fontWeight: 600 }}>{item.student?.full_name}</div>
                                            <div className="mcr-table-meta">{item.student?.nis}</div>
                                        </td>
                                        <td>{item.leave_date}</td>
                                        <td>{item.return_date ?? '-'}</td>
                                        <td>
                                            <span className={`mcr-dot-badge ${
                                                item.status === 'approved'
                                                    ? 'active'
                                                    : item.status === 'rejected'
                                                        ? 'wafat'
                                                        : 'keluar'
                                            }`}
                                            >
                                                {item.status}
                                            </span>
                                        </td>
                                        <td style={{ maxWidth: 260 }}>{item.reason}</td>
                                        <td>
                                            <div className="mcr-action-group">
                                                {item.status === 'pending' ? (
                                                    <>
                                                        <button type="button" className="mcr-btn secondary" onClick={() => setApproveTarget(item)}>
                                                            Setujui
                                                        </button>
                                                        <button type="button" className="mcr-btn danger" onClick={() => setRejectTarget(item)}>
                                                            Tolak
                                                        </button>
                                                    </>
                                                ) : null}
                                                {item.status === 'approved' && !item.actual_return_date ? (
                                                    <button type="button" className="mcr-btn ghost" onClick={() => setReturnedTarget(item)}>
                                                        Tandai Kembali
                                                    </button>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={permissions.links} />
                </CrudCard>
            </div>

            <CrudModal
                open={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title="Pengajuan Izin Baru"
                subtitle="Input data izin santri tanpa pindah halaman."
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setIsCreateOpen(false)} disabled={createForm.processing}>
                            Batal
                        </button>
                        <button type="submit" form="leave-create-form" className="mcr-btn primary" disabled={createForm.processing}>
                            {createForm.processing ? 'Menyimpan...' : 'Simpan Pengajuan'}
                        </button>
                    </>
                )}
            >
                <form id="leave-create-form" className="mcr-form-grid" onSubmit={submitCreate}>
                    <div className="mcr-form-group full">
                        <label>Santri</label>
                        <AppSelect
                            options={studentOptions}
                            value={studentOptions.find((option) => option.value === createForm.data.student_id) ?? null}
                            onChange={(option) => createForm.setData('student_id', String(option?.value ?? ''))}
                            placeholder="Pilih santri"
                        />
                    </div>
                    <div className="mcr-form-group">
                        <label>Tanggal Izin</label>
                        <input
                            className="mcr-form-date"
                            type="date"
                            value={createForm.data.leave_date}
                            onChange={(e) => createForm.setData('leave_date', e.target.value)}
                        />
                    </div>
                    <div className="mcr-form-group">
                        <label>Rencana Kembali</label>
                        <input
                            className="mcr-form-date"
                            type="date"
                            value={createForm.data.return_date}
                            onChange={(e) => createForm.setData('return_date', e.target.value)}
                        />
                    </div>
                    <div className="mcr-form-group full">
                        <label>Alasan</label>
                        <textarea
                            className="mcr-textarea"
                            value={createForm.data.reason}
                            onChange={(e) => createForm.setData('reason', e.target.value)}
                        />
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={approveTarget !== null}
                onClose={() => setApproveTarget(null)}
                onConfirm={approvePermission}
                title="Setujui Izin"
                description={`Setujui izin untuk ${approveTarget?.student?.full_name ?? 'santri'}?`}
                confirmLabel="Setujui"
            />

            <CrudModal
                open={rejectTarget !== null}
                onClose={() => setRejectTarget(null)}
                title="Tolak Izin"
                subtitle="Isi alasan penolakan terlebih dahulu."
                footer={(
                    <>
                        <button type="button" className="mcr-btn ghost" onClick={() => setRejectTarget(null)} disabled={rejectForm.processing}>
                            Batal
                        </button>
                        <button type="button" className="mcr-btn danger" onClick={rejectPermission} disabled={rejectForm.processing}>
                            {rejectForm.processing ? 'Memproses...' : 'Tolak Izin'}
                        </button>
                    </>
                )}
            >
                <div className="mcr-form-group">
                    <label>Alasan Penolakan</label>
                    <textarea
                        className="mcr-textarea"
                        value={rejectForm.data.rejection_reason}
                        onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                    />
                </div>
            </CrudModal>

            <CrudConfirmModal
                open={returnedTarget !== null}
                onClose={() => setReturnedTarget(null)}
                onConfirm={markReturned}
                title="Tandai Santri Kembali"
                description={`Tandai ${returnedTarget?.student?.full_name ?? 'santri'} sudah kembali?`}
                confirmLabel="Ya, Sudah Kembali"
            />
        </AppLayout>
    );
}
