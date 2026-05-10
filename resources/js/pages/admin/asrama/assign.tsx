import { Head, router, useForm } from '@inertiajs/react';
import { BedDouble, CheckCircle2, Download, FileText, FileUp, Home, Plus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
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
    openDownload,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, BreadcrumbItem, DormRoom, PaginatedData, Student } from '@/types';

type AvailableRoom = DormRoom & { building?: { id: number; name: string } };

type Props = {
    unassignedStudents: PaginatedData<Pick<Student, 'id' | 'nis' | 'full_name'>>;
    availableRooms: AvailableRoom[];
    filters: { per_page?: string; academic_year_id?: string };
    perPageOptions: number[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    selectedAcademicYearId: number;
    copySourceAcademicYear?: Pick<AcademicYear, 'id' | 'name'> | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asrama', href: '/admin/asrama' },
    { title: 'Assign Kamar', href: '/admin/asrama/assign' },
];

export default function AsramaAssign({
    unassignedStudents,
    availableRooms,
    filters,
    perPageOptions,
    academicYears,
    selectedAcademicYearId,
    copySourceAcademicYear,
}: Props) {
    const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
    const [copyProcessing, setCopyProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const assignForm = useForm<{ student_ids: number[]; room_id: string; checkin_date: string }>({
        student_ids: [],
        room_id: '',
        checkin_date: new Date().toISOString().slice(0, 10),
    });
    const currentAcademicYearId = Number(filters.academic_year_id ?? String(selectedAcademicYearId));

    const importForm = useForm<{
        file: File | null;
        strategy: 'skip' | 'update';
    }>({
        file: null,
        strategy: 'skip',
    });

    const availableSlots = useMemo(
        () => availableRooms.reduce((sum, room) => sum + Math.max(0, room.capacity - (room.occupants_count ?? 0)), 0),
        [availableRooms],
    );

    function setPerPage(value: string) {
        router.get('/admin/asrama/assign', { per_page: value, academic_year_id: currentAcademicYearId }, { preserveState: true, preserveScroll: true });
    }

    function setAcademicYear(value: string) {
        router.get('/admin/asrama/assign', { per_page: filters.per_page ?? perPageOptions[0], academic_year_id: value }, { preserveState: true, preserveScroll: true });
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
        assignForm.transform((data) => ({ ...data, academic_year_id: currentAcademicYearId }));
        assignForm.post('/admin/asrama/assignments', {
            onSuccess: () => {
                setSelectedStudents([]);
                assignForm.setData('student_ids', []);
                toast.success('Santri berhasil ditempatkan');
            },
            onError: () => toast.error('Gagal melakukan penempatan kamar'),
        });
    }

    function submitImport(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/asrama/import', {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
                toast.success('Impor asrama selesai');
            },
            onError: () => toast.error('Gagal mengimpor file'),
        });
    }

    function copyFromPreviousAcademicYear() {
        if (!copySourceAcademicYear) return;
        const ok = window.confirm(`Salin assignment dari ${copySourceAcademicYear.name} ke tahun ajaran aktif ini? Data santri yang sudah punya kobong akan di-skip.`);
        if (!ok) return;

        setCopyProcessing(true);
        router.post('/admin/asrama/assignments/copy-from-year', {
            source_academic_year_id: copySourceAcademicYear.id,
            target_academic_year_id: currentAcademicYearId,
            checkin_date: assignForm.data.checkin_date,
        }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Copy assignment selesai'),
            onError: () => toast.error('Gagal copy assignment'),
            onFinish: () => setCopyProcessing(false),
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
                            <select className="mcr-filter-select" value={String(currentAcademicYearId)} onChange={(e) => setAcademicYear(e.target.value)}>
                                {academicYears.map((year) => (
                                    <option key={year.id} value={String(year.id)}>{year.name}</option>
                                ))}
                            </select>
                            <span className="mcr-table-meta">Pilih santri, lalu tentukan kamar dan tanggal check-in.</span>
                        </>
                    }
                    right={
                        <>
                            <button type="button" className="mcr-btn ghost" onClick={() => openDownload('/admin/asrama/template?format=xlsx')}>
                                <FileText size={14} />
                                Template
                            </button>
                            <button
                                type="button"
                                className="mcr-btn ghost"
                                onClick={() => openDownload(`/admin/asrama/export/assignments?academic_year_id=${currentAcademicYearId}&format=xlsx`)}
                            >
                                <Download size={14} />
                                Export penempatan
                            </button>
                            <button type="button" className="mcr-btn secondary" onClick={() => setImportOpen(true)}>
                                <FileUp size={14} />
                                Impor Excel
                            </button>
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
                    {copySourceAcademicYear ? (
                        <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="button" className="mcr-btn secondary" onClick={copyFromPreviousAcademicYear} disabled={copyProcessing}>
                                {copyProcessing ? 'Menyalin...' : `Copy dari ${copySourceAcademicYear.name}`}
                            </button>
                        </div>
                    ) : null}
                    <CrudBulkActionBar visible={selectedStudents.length > 0} selectedCount={selectedStudents.length} onClear={() => setSelectedStudents([])}>
                        <button type="button" className="mcr-btn primary" onClick={submitAssign} disabled={assignForm.processing}>
                            <Plus size={14} />
                            {assignForm.processing ? 'Memproses...' : 'Tempatkan Santri'}
                        </button>
                    </CrudBulkActionBar>
                </CrudCard>
            </div>

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Impor Asrama (Excel)"
                subtitle="Sheet Gedung_Kamar dan Penempatan. Santri yang sudah punya kobong aktif di tahun ajaran baris akan dilewati."
            >
                <form onSubmit={submitImport}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="assign-asrama-import-file">File</label>
                            <input
                                id="assign-asrama-import-file"
                                className="mcr-input"
                                type="file"
                                accept=".xlsx,.xls"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={importForm.errors.file} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="assign-asrama-import-strategy">Strategi kamar duplikat</label>
                            <select
                                id="assign-asrama-import-strategy"
                                className="mcr-form-select"
                                value={importForm.data.strategy}
                                onChange={(e) => importForm.setData('strategy', e.target.value as 'skip' | 'update')}
                            >
                                <option value="skip">Lewati kamar yang nomornya sudah ada</option>
                                <option value="update">Perbarui kapasitas/lantai kamar yang sudah ada</option>
                            </select>
                            <InputError message={importForm.errors.strategy} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setImportOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={importForm.processing}>
                            {importForm.processing ? 'Memproses…' : 'Proses impor'}
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
