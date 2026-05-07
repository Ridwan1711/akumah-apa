import { Head, router, useForm } from '@inertiajs/react';
import { BedDouble, CheckCircle2, Home, Plus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudBulkActionBar,
    CrudCard,
    CrudEmptyState,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DormRoom, PaginatedData, Student } from '@/types';

type AvailableRoom = DormRoom & { building?: { id: number; name: string } };

type Props = {
    unassignedStudents: PaginatedData<Pick<Student, 'id' | 'nis' | 'full_name'>>;
    availableRooms: AvailableRoom[];
    filters: { per_page?: string };
    perPageOptions: number[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asrama', href: '/admin/asrama' },
    { title: 'Assign Kamar', href: '/admin/asrama/assign' },
];

export default function AsramaAssign({ unassignedStudents, availableRooms, filters, perPageOptions }: Props) {
    const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
    const assignForm = useForm<{ student_ids: number[]; room_id: string; checkin_date: string }>({
        student_ids: [],
        room_id: '',
        checkin_date: new Date().toISOString().slice(0, 10),
    });

    const availableSlots = useMemo(
        () => availableRooms.reduce((sum, room) => sum + Math.max(0, room.capacity - (room.occupants_count ?? 0)), 0),
        [availableRooms],
    );

    function setPerPage(value: string) {
        router.get('/admin/asrama/assign', { per_page: value }, { preserveState: true, preserveScroll: true });
    }

    function toggleStudent(id: number) {
        setSelectedStudents((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function toggleAllStudents() {
        const allIds = unassignedStudents.data.map((s) => s.id);
        const allChecked = allIds.length > 0 && allIds.every((id) => selectedStudents.includes(id));
        setSelectedStudents(allChecked ? [] : allIds);
    }

    function submitAssign() {
        if (selectedStudents.length === 0) {
            toast.error('Pilih minimal satu santri');
            return;
        }
        assignForm.setData('student_ids', selectedStudents);
        assignForm.post('/admin/asrama/assignments', {
            onSuccess: () => {
                setSelectedStudents([]);
                assignForm.setData('student_ids', []);
                toast.success('Santri berhasil ditempatkan');
            },
            onError: () => toast.error('Gagal melakukan penempatan kamar'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Assign Kamar Asrama" />
            <div>
                <CrudPageHeader title="Assign Kamar Asrama" description="Tempatkan santri aktif yang belum memiliki kamar asrama." />
                <CrudStatStrip
                    items={[
                        { key: 'unassigned', label: 'Santri Belum Kamar', value: unassignedStudents.total, icon: <Users size={18} />, tone: 'blue' },
                        { key: 'rooms', label: 'Kamar Tersedia', value: availableRooms.length, icon: <BedDouble size={18} />, tone: 'green' },
                        { key: 'slots', label: 'Slot Kosong', value: availableSlots, icon: <Home size={18} />, tone: 'amber' },
                        { key: 'selected', label: 'Terpilih', value: selectedStudents.length, icon: <CheckCircle2 size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select className="mcr-filter-select" value={filters.per_page ?? String(perPageOptions[0] ?? 25)} onChange={(e) => setPerPage(e.target.value)}>
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>{opt} / halaman</option>
                                ))}
                            </select>
                            <span className="mcr-table-meta">Pilih santri, lalu tentukan kamar dan tanggal check-in.</span>
                        </>
                    }
                />

                <CrudCard title="Daftar Santri Belum Kamar">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <input type="checkbox" className="mcr-check" checked={unassignedStudents.data.length > 0 && unassignedStudents.data.every((s) => selectedStudents.includes(s.id))} onChange={toggleAllStudents} />
                                    </th>
                                    <th>NIS</th>
                                    <th>Nama Santri</th>
                                </tr>
                            </thead>
                            <tbody>
                                {unassignedStudents.data.length === 0 ? (
                                    <tr><td colSpan={3}><CrudEmptyState title="Tidak ada data" description="Semua santri aktif sudah memiliki kamar." /></td></tr>
                                ) : (
                                    unassignedStudents.data.map((student) => (
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
                    <CrudPagination links={unassignedStudents.links} />
                </CrudCard>

                <CrudCard title="Form Penempatan" subtitle="Pilih kamar tujuan dan tanggal mulai menempati kamar.">
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="assign-room">Kamar Tujuan</label>
                            <select id="assign-room" className="mcr-form-select" value={assignForm.data.room_id} onChange={(e) => assignForm.setData('room_id', e.target.value)}>
                                <option value="">Pilih kamar</option>
                                {availableRooms.map((room) => {
                                    const freeSlot = Math.max(0, room.capacity - (room.occupants_count ?? 0));
                                    return (
                                        <option key={room.id} value={String(room.id)}>
                                            {room.building?.name ?? 'Gedung'} - {room.room_number} (sisa {freeSlot})
                                        </option>
                                    );
                                })}
                            </select>
                            <InputError message={assignForm.errors.room_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="assign-checkin">Tanggal Check-in</label>
                            <input id="assign-checkin" type="date" className="mcr-input" value={assignForm.data.checkin_date} onChange={(e) => assignForm.setData('checkin_date', e.target.value)} />
                            <InputError message={assignForm.errors.checkin_date} />
                        </div>
                    </div>
                    <CrudBulkActionBar visible={selectedStudents.length > 0} selectedCount={selectedStudents.length} onClear={() => setSelectedStudents([])}>
                        <button type="button" className="mcr-btn primary" onClick={submitAssign} disabled={assignForm.processing}>
                            <Plus size={14} />
                            {assignForm.processing ? 'Memproses...' : 'Tempatkan Santri'}
                        </button>
                    </CrudBulkActionBar>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
