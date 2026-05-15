import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, GraduationCap, Send, XCircle } from 'lucide-react';
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
import type {
    AcademicYear,
    BreadcrumbItem,
    FormalContinuationRound,
    PaginatedData,
    StudentFormalContinuationRequest,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Lanjut MA10 / Kuliah', href: '/admin/formal-continuation' },
];

const statusLabels: Record<string, string> = {
    awaiting_confirmations: 'Konfirmasi',
    pending_admin: 'Menunggu admin',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Batal',
};

const choiceLabel = (c: string | null) => {
    if (c === 'ma_10') return 'MA 10';
    if (c === 'kuliah') return 'Kuliah';
    return '—';
};

type Props = {
    requests: PaginatedData<StudentFormalContinuationRequest>;
    filters: { status?: string; search?: string };
    rounds: FormalContinuationRound[];
    academicYears: AcademicYear[];
    previewEligibleCount: number | null;
    previewSourceYearId: number | null;
    existingRoundSourceIds: number[];
};

export default function FormalContinuationIndex({
    requests,
    filters,
    rounds,
    academicYears,
    previewEligibleCount,
    previewSourceYearId,
    existingRoundSourceIds,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [approveTarget, setApproveTarget] = useState<StudentFormalContinuationRequest | null>(null);
    const [rejectTarget, setRejectTarget] = useState<StudentFormalContinuationRequest | null>(null);
    const rejectForm = useForm({ rejection_reason: '', admin_notes: '' });
    const approveForm = useForm({ admin_notes: '' });

    const sendForm = useForm({
        source_academic_year_id: previewSourceYearId ?? (academicYears[0]?.id ?? ''),
        target_academic_year_id: '',
    });

    const pendingAdmin = useMemo(
        () => requests.data.filter((r) => r.status === 'pending_admin').length,
        [requests.data],
    );

    const sourceAlreadySent = existingRoundSourceIds.includes(Number(sendForm.data.source_academic_year_id));

    function visitFilters(next: Partial<Props['filters']>) {
        router.get(
            '/admin/formal-continuation',
            {
                ...filters,
                ...next,
                search: (next.search ?? filters.search ?? '').trim() || undefined,
                status: (next.status ?? filters.status) === 'all' ? undefined : next.status ?? filters.status,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function previewEligible() {
        const id = Number(sendForm.data.source_academic_year_id);
        if (!id) return;
        router.get('/admin/formal-continuation', { source_academic_year_id: id }, { preserveState: true });
    }

    function sendRound() {
        if (!confirm('Kirim undangan konfirmasi ke semua santri MTs 9 & MA 12 di tahun ajaran sumber?')) return;
        sendForm.post('/admin/formal-continuation/send', { preserveScroll: true });
    }

    function approve() {
        if (!approveTarget) return;
        approveForm.post(`/admin/formal-continuation/${approveTarget.id}/approve`, {
            preserveScroll: true,
            onFinish: () => {
                setApproveTarget(null);
                approveForm.reset();
            },
        });
    }

    function reject() {
        if (!rejectTarget) return;
        rejectForm.post(`/admin/formal-continuation/${rejectTarget.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectTarget(null);
                rejectForm.reset();
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Lanjut MA10 / Kuliah" />
            <div className="space-y-6">
                <CrudPageHeader
                    title="Lanjut MA10 / Kuliah"
                    description="Undangan konfirmasi untuk santri MTs 9 (MA 10 atau Kuliah) dan MA 12 (Kuliah). Santri + wali mengisi; wali override. Fallback otomatis 2 bulan sebelum TA berakhir."
                />
                <CrudStatStrip
                    items={[
                        {
                            key: 'pending',
                            label: 'Menunggu admin',
                            value: pendingAdmin,
                            icon: <GraduationCap size={18} />,
                            tone: 'amber',
                        },
                    ]}
                />
                <FlashMessage />

                <CrudCard title="Kirim undangan (manual)">
                    <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="text-xs font-medium text-muted-foreground">TA sumber (MTs 9 / MA 12)</label>
                            <select
                                className="mcr-filter-select mt-1 w-full"
                                value={sendForm.data.source_academic_year_id}
                                onChange={(e) => sendForm.setData('source_academic_year_id', e.target.value)}
                            >
                                {academicYears.map((y) => (
                                    <option key={y.id} value={y.id}>
                                        {y.name}
                                        {existingRoundSourceIds.includes(y.id) ? ' (sudah dikirim)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-medium text-muted-foreground">TA tujuan enrollment</label>
                            <select
                                className="mcr-filter-select mt-1 w-full"
                                value={sendForm.data.target_academic_year_id}
                                onChange={(e) => sendForm.setData('target_academic_year_id', e.target.value)}
                            >
                                <option value="">Pilih...</option>
                                {academicYears.map((y) => (
                                    <option key={y.id} value={y.id}>
                                        {y.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col justify-end gap-2">
                            <button type="button" className="mcr-btn ghost" onClick={previewEligible}>
                                Pratinjau jumlah
                            </button>
                            {previewEligibleCount != null && previewSourceYearId === Number(sendForm.data.source_academic_year_id) ? (
                                <p className="text-sm text-muted-foreground">
                                    Santri eligible: <strong>{previewEligibleCount}</strong>
                                </p>
                            ) : null}
                        </div>
                        <div className="flex items-end">
                            <button
                                type="button"
                                className="mcr-btn primary w-full"
                                disabled={
                                    sendForm.processing ||
                                    sourceAlreadySent ||
                                    !sendForm.data.target_academic_year_id
                                }
                                onClick={sendRound}
                            >
                                <Send size={14} className="inline mr-1" />
                                {sourceAlreadySent ? 'Sudah dikirim' : 'Kirim undangan'}
                            </button>
                        </div>
                    </div>
                </CrudCard>

                {rounds.length > 0 ? (
                    <CrudCard title="Round terakhir">
                        <CrudTableShell>
                            <table className="mcr-table">
                                <thead>
                                    <tr>
                                        <th>TA sumber</th>
                                        <th>TA tujuan</th>
                                        <th>Trigger</th>
                                        <th>Dikirim</th>
                                        <th>Permohonan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rounds.map((r) => (
                                        <tr key={r.id}>
                                            <td>{r.source_academic_year?.name}</td>
                                            <td>{r.target_academic_year?.name}</td>
                                            <td>{r.trigger_type === 'fallback' ? 'Otomatis' : 'Admin'}</td>
                                            <td>{new Date(r.sent_at).toLocaleString('id-ID')}</td>
                                            <td>
                                                {r.requests_created} / {r.eligible_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CrudTableShell>
                    </CrudCard>
                ) : null}

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
                <CrudCard title="Daftar konfirmasi">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Santri</th>
                                    <th>Tingkat</th>
                                    <th>TA tujuan</th>
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
                                        <td colSpan={8} className="text-center text-muted-foreground py-8">
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
                                            <td>{row.current_tingkat_sekolah?.name ?? row.current_tingkat_code}</td>
                                            <td>{row.target_academic_year?.name}</td>
                                            <td>{statusLabels[row.status] ?? row.status}</td>
                                            <td>{choiceLabel(row.santri_choice)}</td>
                                            <td>{choiceLabel(row.wali_choice)}</td>
                                            <td>{choiceLabel(row.resolved_choice)}</td>
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
                title="Setujui lanjut formal?"
                description={
                    approveTarget
                        ? `Enrollment TA ${approveTarget.target_academic_year?.name} untuk ${approveTarget.student?.full_name} akan diperbarui (${choiceLabel(approveTarget.resolved_choice)}).`
                        : ''
                }
                confirmLabel="Setujui"
                onConfirm={approve}
                loading={approveForm.processing}
            />

            <CrudModal open={!!rejectTarget} onClose={() => setRejectTarget(null)} title="Tolak konfirmasi">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        reject();
                    }}
                >
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
