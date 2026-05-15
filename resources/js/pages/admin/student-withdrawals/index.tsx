import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, LogOut, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudConfirmModal,
    CrudModal,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, StudentWithdrawalRequest } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Keluar Pesantren', href: '/admin/student-withdrawals' },
];

type Props = {
    requests: PaginatedData<StudentWithdrawalRequest>;
    filters: { status?: string; search?: string };
};

const statusLabels: Record<string, string> = {
    awaiting_confirmations: 'Konfirmasi',
    pending_admin: 'Menunggu admin',
    closed_continue: 'Tetap',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Batal',
};

export default function StudentWithdrawalIndex({ requests, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [approveTarget, setApproveTarget] = useState<StudentWithdrawalRequest | null>(null);
    const [rejectTarget, setRejectTarget] = useState<StudentWithdrawalRequest | null>(null);
    const rejectForm = useForm({ rejection_reason: '', admin_notes: '' });
    const approveForm = useForm({ admin_notes: '' });

    const pendingAdmin = useMemo(
        () => requests.data.filter((r) => r.status === 'pending_admin').length,
        [requests.data],
    );

    function visitFilters(next: Partial<Props['filters']>) {
        router.get(
            '/admin/student-withdrawals',
            {
                ...filters,
                ...next,
                search: (next.search ?? filters.search ?? '').trim() || undefined,
                status: (next.status ?? filters.status) === 'all' ? undefined : next.status ?? filters.status,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function approve() {
        if (!approveTarget) return;
        approveForm.post(`/admin/student-withdrawals/${approveTarget.id}/approve`, {
            preserveScroll: true,
            onFinish: () => {
                setApproveTarget(null);
                approveForm.reset();
            },
        });
    }

    function reject() {
        if (!rejectTarget) return;
        rejectForm.post(`/admin/student-withdrawals/${rejectTarget.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectTarget(null);
                rejectForm.reset();
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Keluar Pesantren" />
            <div>
                <CrudPageHeader
                    title="Permohonan keluar pesantren"
                    description="Konfirmasi santri + wali (wali override). Admin menyetujui sebelum status keluar."
                />
                <CrudStatStrip
                    items={[
                        {
                            key: 'pending',
                            label: 'Menunggu admin',
                            value: pendingAdmin,
                            icon: <LogOut size={18} />,
                            tone: 'amber',
                        },
                    ]}
                />
                <FlashMessage />
                <CrudToolbar
                    left={
                        <>
                            <input
                                className="mcr-input"
                                placeholder="Cari nama / NIS..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && visitFilters({ search })}
                            />
                            <select
                                className="mcr-filter-select"
                                value={filters.status ?? 'all'}
                                onChange={(e) => visitFilters({ status: e.target.value })}
                            >
                                <option value="all">Semua status</option>
                                <option value="pending_admin">Menunggu admin</option>
                                <option value="awaiting_confirmations">Konfirmasi</option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                            </select>
                        </>
                    }
                />
                <CrudCard title="Daftar permohonan">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Santri</th>
                                    <th>Status</th>
                                    <th>Santri</th>
                                    <th>Wali</th>
                                    <th>Efektif</th>
                                    <th style={{ width: 140 }} />
                                </tr>
                            </thead>
                            <tbody>
                                {requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="text-center text-muted-foreground py-8">
                                            Belum ada permohonan.
                                        </td>
                                    </tr>
                                ) : (
                                    requests.data.map((row) => (
                                        <tr key={row.id}>
                                            <td>
                                                <div className="font-medium">{row.student?.full_name}</div>
                                                <div className="text-xs text-muted-foreground">{row.student?.nis}</div>
                                            </td>
                                            <td>{statusLabels[row.status] ?? row.status}</td>
                                            <td>{row.santri_choice ?? '—'}</td>
                                            <td>{row.wali_choice ?? '—'}</td>
                                            <td>{row.effective_date ?? '—'}</td>
                                            <td>
                                                {row.status === 'pending_admin' ? (
                                                    <div className="mcr-action-group">
                                                        <button
                                                            type="button"
                                                            className="mcr-icon-action"
                                                            title="Setujui"
                                                            onClick={() => setApproveTarget(row)}
                                                        >
                                                            <CheckCircle2 size={13} />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="mcr-icon-action danger"
                                                            title="Tolak"
                                                            onClick={() => setRejectTarget(row)}
                                                        >
                                                            <XCircle size={13} />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                </CrudCard>
                <CrudPagination links={requests.links} />
            </div>

            <CrudConfirmModal
                open={!!approveTarget}
                onClose={() => setApproveTarget(null)}
                title="Setujui keluar pesantren?"
                description={
                    approveTarget
                        ? `${approveTarget.student?.full_name} akan berstatus keluar setelah disetujui.`
                        : ''
                }
                confirmLabel="Setujui"
                onConfirm={approve}
                loading={approveForm.processing}
            />

            <CrudModal open={!!rejectTarget} onClose={() => setRejectTarget(null)} title="Tolak permohonan">
                <form onSubmit={(e) => { e.preventDefault(); reject(); }}>
                    <div className="mcr-form-group full">
                        <label htmlFor="rejection_reason">Alasan penolakan</label>
                        <textarea
                            id="rejection_reason"
                            className="mcr-textarea"
                            value={rejectForm.data.rejection_reason}
                            onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                            required
                        />
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setRejectTarget(null)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={rejectForm.processing}>
                            Tolak
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
