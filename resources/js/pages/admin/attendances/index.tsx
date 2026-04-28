import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Pencil, UserCheck, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
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
import type { BreadcrumbItem, PaginatedData, SchoolClass } from '@/types';
import { toast } from 'sonner';

type AttendanceRow = {
    id: number;
    status: string;
    notes?: string | null;
    marked_at?: string | null;
    lesson_session_id?: number;
    student?: { id: number; nis: string; full_name: string };
    lesson_session?: {
        id: number;
        date?: string | null;
        schedule?: {
            school_class?: { id: number; name: string; grade_level_id?: number | null };
            subject?: { id: number; name: string };
        };
    };
};

type Props = {
    attendances: PaginatedData<AttendanceRow>;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    filters: {
        class_id?: string;
        student_id?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
    };
};

type EditForm = { status: string; notes: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kehadiran Santri', href: '/admin/attendances' },
];

const statuses = ['hadir', 'izin', 'sakit', 'alpa'];

export default function AttendanceIndex({ attendances, classes, filters }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [editing, setEditing] = useState<AttendanceRow | null>(null);

    const editForm = useForm<EditForm>({ status: 'hadir', notes: '' });

    function applyFilters(next: Partial<Props['filters']> = {}) {
        router.get('/admin/attendances', { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    }

    function openEdit(item: AttendanceRow) {
        setEditing(item);
        editForm.setData({
            status: item.status ?? 'hadir',
            notes: item.notes ?? '',
        });
        editForm.clearErrors();
        setEditOpen(true);
    }

    function submitEdit(e: React.FormEvent) {
        if (!editing) return;
        e.preventDefault();
        editForm.put(`/admin/attendances/${editing.id}`, {
            onSuccess: () => {
                setEditOpen(false);
                setEditing(null);
                toast.success('Kehadiran diperbarui');
            },
            onError: () => toast.error('Gagal memperbarui kehadiran'),
        });
    }

    const hadirCount = useMemo(
        () => attendances.data.filter((row) => row.status === 'hadir').length,
        [attendances.data],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kehadiran Santri" />
            <div>
                <CrudPageHeader
                    title="Kehadiran Santri"
                    description="Review dan koreksi data kehadiran yang sudah diinput guru."
                />
                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Data', value: attendances.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'hadir', label: 'Hadir (Halaman)', value: hadirCount, icon: <UserCheck size={18} />, tone: 'green' },
                        { key: 'kelas', label: 'Filter Kelas', value: filters.class_id ? 'Aktif' : 'Semua', icon: <CalendarDays size={18} />, tone: 'amber' },
                        { key: 'status', label: 'Status Filter', value: filters.status || 'all', icon: <CheckCircle2 size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select className="mcr-filter-select" value={filters.class_id ?? 'all'} onChange={(e) => applyFilters({ class_id: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Kelas</option>
                                {classes.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.status ?? 'all'} onChange={(e) => applyFilters({ status: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Status</option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>{status}</option>
                                ))}
                            </select>
                            <input type="date" className="mcr-input" value={filters.date_from ?? ''} onChange={(e) => applyFilters({ date_from: e.target.value || undefined })} />
                            <input type="date" className="mcr-input" value={filters.date_to ?? ''} onChange={(e) => applyFilters({ date_to: e.target.value || undefined })} />
                        </>
                    }
                />

                <CrudCard>
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th>Santri</th>
                                    <th>Kelas</th>
                                    <th>Mapel</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Catatan</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {attendances.data.length === 0 ? (
                                    <tr><td colSpan={7}><CrudEmptyState title="Tidak ada data kehadiran" description="Ubah filter untuk melihat data kehadiran." /></td></tr>
                                ) : (
                                    attendances.data.map((row) => (
                                        <tr key={row.id}>
                                            <td>{row.student?.full_name ?? '-'}</td>
                                            <td>{row.lesson_session?.schedule?.school_class?.name ?? '-'}</td>
                                            <td>{row.lesson_session?.schedule?.subject?.name ?? '-'}</td>
                                            <td>{row.lesson_session?.date ?? '-'}</td>
                                            <td><span className="mcr-dot-badge alumni">{row.status}</span></td>
                                            <td>{row.notes ?? '-'}</td>
                                            <td>
                                                <div className="mcr-action-group">
                                                    <button type="button" className="mcr-icon-action" onClick={() => openEdit(row)} title="Edit kehadiran">
                                                        <Pencil size={13} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                    <CrudPagination links={attendances.links} />
                </CrudCard>
            </div>

            <CrudModal open={editOpen} onClose={() => setEditOpen(false)} title="Edit Kehadiran" subtitle={`Perbarui status kehadiran untuk ${editing?.student?.full_name ?? '-'}.`}>
                <form onSubmit={submitEdit}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="attendance-status">Status</label>
                            <select id="attendance-status" className="mcr-form-select" value={editForm.data.status} onChange={(e) => editForm.setData('status', e.target.value)}>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>{status}</option>
                                ))}
                            </select>
                            <InputError message={editForm.errors.status} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="attendance-notes">Catatan</label>
                            <textarea id="attendance-notes" className="mcr-textarea" value={editForm.data.notes} onChange={(e) => editForm.setData('notes', e.target.value)} />
                            <InputError message={editForm.errors.notes} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setEditOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={editForm.processing}>{editForm.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
