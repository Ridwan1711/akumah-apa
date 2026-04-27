import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    CrudCard,
    CrudConfirmModal,
    CrudEmptyState,
    CrudModal,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, BreadcrumbItem, Semester } from '@/types';
import { toast } from 'sonner';

type Props = {
    academicYears: (AcademicYear & { semesters?: Semester[] })[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tahun Ajaran', href: '/admin/academic-years' },
];

type YearForm = {
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

type SemesterForm = {
    academic_year_id: string;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

const initialYearForm: YearForm = {
    name: '',
    start_date: '',
    end_date: '',
    is_active: false,
};

const initialSemesterForm: SemesterForm = {
    academic_year_id: '',
    name: '',
    start_date: '',
    end_date: '',
    is_active: false,
};

export default function AcademicYearIndex({ academicYears }: Props) {
    const [yearModalOpen, setYearModalOpen] = useState(false);
    const [editingYear, setEditingYear] = useState<AcademicYear | null>(null);
    const [semesterModalOpen, setSemesterModalOpen] = useState(false);
    const [editingSemester, setEditingSemester] = useState<Semester | null>(null);
    const [deleteYearTarget, setDeleteYearTarget] = useState<AcademicYear | null>(null);
    const [deleteSemesterTarget, setDeleteSemesterTarget] = useState<Semester | null>(null);

    const yearForm = useForm<YearForm>(initialYearForm);
    const semesterForm = useForm<SemesterForm>(initialSemesterForm);

    const activeYearCount = useMemo(
        () => academicYears.filter((item) => item.is_active).length,
        [academicYears],
    );
    const semesterCount = useMemo(
        () => academicYears.reduce((sum, item) => sum + (item.semesters?.length ?? 0), 0),
        [academicYears],
    );

    function openCreateYear() {
        setEditingYear(null);
        yearForm.setData(initialYearForm);
        yearForm.clearErrors();
        setYearModalOpen(true);
    }

    function openEditYear(item: AcademicYear) {
        setEditingYear(item);
        yearForm.setData({
            name: item.name,
            start_date: item.start_date,
            end_date: item.end_date,
            is_active: item.is_active,
        });
        yearForm.clearErrors();
        setYearModalOpen(true);
    }

    function submitYear(e: React.FormEvent) {
        e.preventDefault();
        if (editingYear) {
            yearForm.put(`/admin/academic-years/${editingYear.id}`, {
                onSuccess: () => {
                    setYearModalOpen(false);
                    setEditingYear(null);
                    toast.success('Tahun ajaran diperbarui');
                },
                onError: () => toast.error('Gagal memperbarui tahun ajaran'),
            });
            return;
        }
        yearForm.post('/admin/academic-years', {
            onSuccess: () => {
                setYearModalOpen(false);
                toast.success('Tahun ajaran ditambahkan');
            },
            onError: () => toast.error('Gagal menambah tahun ajaran'),
        });
    }

    function openCreateSemester(academicYearId?: number) {
        setEditingSemester(null);
        semesterForm.setData({
            ...initialSemesterForm,
            academic_year_id: academicYearId ? String(academicYearId) : '',
        });
        semesterForm.clearErrors();
        setSemesterModalOpen(true);
    }

    function openEditSemester(item: Semester) {
        setEditingSemester(item);
        semesterForm.setData({
            academic_year_id: String(item.academic_year_id),
            name: item.name,
            start_date: item.start_date,
            end_date: item.end_date,
            is_active: item.is_active,
        });
        semesterForm.clearErrors();
        setSemesterModalOpen(true);
    }

    function submitSemester(e: React.FormEvent) {
        e.preventDefault();
        if (editingSemester) {
            semesterForm.put(`/admin/semesters/${editingSemester.id}`, {
                onSuccess: () => {
                    setSemesterModalOpen(false);
                    setEditingSemester(null);
                    toast.success('Semester diperbarui');
                },
                onError: () => toast.error('Gagal memperbarui semester'),
            });
            return;
        }
        semesterForm.post('/admin/semesters', {
            onSuccess: () => {
                setSemesterModalOpen(false);
                toast.success('Semester ditambahkan');
            },
            onError: () => toast.error('Gagal menambah semester'),
        });
    }

    function deleteYear() {
        if (!deleteYearTarget) return;
        router.delete(`/admin/academic-years/${deleteYearTarget.id}`, {
            onSuccess: () => {
                setDeleteYearTarget(null);
                toast.success('Tahun ajaran dihapus');
            },
            onError: () => toast.error('Gagal menghapus tahun ajaran'),
        });
    }

    function deleteSemester() {
        if (!deleteSemesterTarget) return;
        router.delete(`/admin/semesters/${deleteSemesterTarget.id}`, {
            onSuccess: () => {
                setDeleteSemesterTarget(null);
                toast.success('Semester dihapus');
            },
            onError: () => toast.error('Gagal menghapus semester'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tahun Ajaran" />
            <div>
                <CrudPageHeader
                    title="Tahun Ajaran & Semester"
                    description="Kelola periode akademik aktif untuk seluruh modul diniyah."
                />

                <CrudStatStrip
                    items={[
                        { key: 'years', label: 'Total Tahun Ajaran', value: academicYears.length, icon: <CalendarDays size={18} />, tone: 'blue' },
                        { key: 'active', label: 'Periode Aktif', value: activeYearCount, icon: <Layers size={18} />, tone: 'green' },
                        { key: 'semesters', label: 'Total Semester', value: semesterCount, icon: <CalendarDays size={18} />, tone: 'amber' },
                        { key: 'latest', label: 'Tahun Terakhir', value: academicYears[0]?.name ?? '-', icon: <Layers size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={<span className="mcr-table-meta">Atur tahun ajaran aktif dan daftar semester per tahun.</span>}
                    right={
                        <>
                            <button type="button" className="mcr-btn secondary" onClick={() => openCreateSemester()}>
                                <Plus size={14} />
                                Tambah Semester
                            </button>
                            <button type="button" className="mcr-btn primary" onClick={openCreateYear}>
                                <Plus size={14} />
                                Tambah Tahun Ajaran
                            </button>
                        </>
                    }
                />

                {academicYears.length === 0 ? (
                    <CrudCard>
                        <CrudEmptyState
                            title="Belum ada tahun ajaran"
                            description="Tambahkan tahun ajaran pertama untuk mulai mengelola semester."
                        />
                    </CrudCard>
                ) : (
                    academicYears.map((year) => (
                        <CrudCard
                            key={year.id}
                            title={year.name}
                            subtitle={`${year.start_date} s/d ${year.end_date}`}
                            right={
                                <div className="mcr-action-group">
                                    {year.is_active ? <span className="mcr-dot-badge active">Aktif</span> : <span className="mcr-dot-badge keluar">Nonaktif</span>}
                                    <button type="button" className="mcr-icon-action" onClick={() => openEditYear(year)} title="Edit tahun ajaran">
                                        <Pencil size={13} />
                                    </button>
                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteYearTarget(year)} title="Hapus tahun ajaran">
                                        <Trash2 size={13} />
                                    </button>
                                    <button type="button" className="mcr-btn secondary" onClick={() => openCreateSemester(year.id)}>
                                        <Plus size={14} />
                                        Semester
                                    </button>
                                </div>
                            }
                        >
                            {!year.semesters || year.semesters.length === 0 ? (
                                <CrudEmptyState title="Belum ada semester" description="Tambahkan semester untuk tahun ajaran ini." />
                            ) : (
                                <div className="mcr-run-list">
                                    {year.semesters.map((semester) => (
                                        <div key={semester.id} className="mcr-run-item">
                                            <div className="mcr-run-top">
                                                <div>
                                                    <strong>{semester.name}</strong>
                                                    <div className="mcr-run-meta">{semester.start_date} s/d {semester.end_date}</div>
                                                </div>
                                                <div className="mcr-action-group">
                                                    {semester.is_active ? <span className="mcr-dot-badge active">Aktif</span> : <span className="mcr-dot-badge keluar">Nonaktif</span>}
                                                    <button type="button" className="mcr-icon-action" onClick={() => openEditSemester(semester)} title="Edit semester">
                                                        <Pencil size={13} />
                                                    </button>
                                                    <button type="button" className="mcr-icon-action danger" onClick={() => setDeleteSemesterTarget(semester)} title="Hapus semester">
                                                        <Trash2 size={13} />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CrudCard>
                    ))
                )}
            </div>

            <CrudModal
                open={yearModalOpen}
                onClose={() => {
                    setYearModalOpen(false);
                    setEditingYear(null);
                }}
                title={editingYear ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran'}
                subtitle="Tahun aktif akan menonaktifkan periode aktif sebelumnya."
            >
                <form onSubmit={submitYear}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="academic-year-name">Nama Tahun Ajaran</label>
                            <input id="academic-year-name" className="mcr-input" value={yearForm.data.name} onChange={(e) => yearForm.setData('name', e.target.value)} placeholder="2025/2026" />
                            <InputError message={yearForm.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="academic-year-start">Tanggal Mulai</label>
                            <input id="academic-year-start" type="date" className="mcr-input" value={yearForm.data.start_date} onChange={(e) => yearForm.setData('start_date', e.target.value)} />
                            <InputError message={yearForm.errors.start_date} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="academic-year-end">Tanggal Selesai</label>
                            <input id="academic-year-end" type="date" className="mcr-input" value={yearForm.data.end_date} onChange={(e) => yearForm.setData('end_date', e.target.value)} />
                            <InputError message={yearForm.errors.end_date} />
                        </div>
                        <div className="mcr-form-group full">
                            <label className="mcr-checkline">
                                <input type="checkbox" checked={yearForm.data.is_active} onChange={(e) => yearForm.setData('is_active', e.target.checked)} />
                                <span>Set sebagai tahun ajaran aktif</span>
                            </label>
                            <InputError message={yearForm.errors.is_active} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setYearModalOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={yearForm.processing}>
                            {yearForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudModal
                open={semesterModalOpen}
                onClose={() => {
                    setSemesterModalOpen(false);
                    setEditingSemester(null);
                }}
                title={editingSemester ? 'Edit Semester' : 'Tambah Semester'}
                subtitle="Semester aktif akan menonaktifkan semester aktif sebelumnya."
            >
                <form onSubmit={submitSemester}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="semester-year">Tahun Ajaran</label>
                            <select id="semester-year" className="mcr-form-select" value={semesterForm.data.academic_year_id} onChange={(e) => semesterForm.setData('academic_year_id', e.target.value)}>
                                <option value="">Pilih tahun ajaran</option>
                                {academicYears.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <InputError message={semesterForm.errors.academic_year_id} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="semester-name">Nama Semester</label>
                            <input id="semester-name" className="mcr-input" value={semesterForm.data.name} onChange={(e) => semesterForm.setData('name', e.target.value)} placeholder="Ganjil / Genap" />
                            <InputError message={semesterForm.errors.name} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="semester-start">Tanggal Mulai</label>
                            <input id="semester-start" type="date" className="mcr-input" value={semesterForm.data.start_date} onChange={(e) => semesterForm.setData('start_date', e.target.value)} />
                            <InputError message={semesterForm.errors.start_date} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="semester-end">Tanggal Selesai</label>
                            <input id="semester-end" type="date" className="mcr-input" value={semesterForm.data.end_date} onChange={(e) => semesterForm.setData('end_date', e.target.value)} />
                            <InputError message={semesterForm.errors.end_date} />
                        </div>
                        <div className="mcr-form-group full">
                            <label className="mcr-checkline">
                                <input type="checkbox" checked={semesterForm.data.is_active} onChange={(e) => semesterForm.setData('is_active', e.target.checked)} />
                                <span>Set sebagai semester aktif</span>
                            </label>
                            <InputError message={semesterForm.errors.is_active} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setSemesterModalOpen(false)}>Batal</button>
                        <button type="submit" className="mcr-btn primary" disabled={semesterForm.processing}>
                            {semesterForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </CrudModal>

            <CrudConfirmModal
                open={deleteYearTarget !== null}
                onClose={() => setDeleteYearTarget(null)}
                onConfirm={deleteYear}
                title="Konfirmasi Hapus Tahun Ajaran"
                description={`Hapus tahun ajaran "${deleteYearTarget?.name ?? '-'}"?`}
                confirmLabel="Hapus Tahun"
            />

            <CrudConfirmModal
                open={deleteSemesterTarget !== null}
                onClose={() => setDeleteSemesterTarget(null)}
                onConfirm={deleteSemester}
                title="Konfirmasi Hapus Semester"
                description={`Hapus semester "${deleteSemesterTarget?.name ?? '-'}"?`}
                confirmLabel="Hapus Semester"
            />
        </AppLayout>
    );
}
