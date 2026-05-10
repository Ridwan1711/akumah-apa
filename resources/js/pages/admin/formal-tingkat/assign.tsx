import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, FileText, FileUp, Layers, Plus, Users } from 'lucide-react';
import { useState } from 'react';
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
import type { AcademicYear, BreadcrumbItem, PaginatedData, Student, TingkatSekolahFormal } from '@/types';

type Props = {
    unassignedStudents: PaginatedData<Pick<Student, 'id' | 'nis' | 'full_name'>>;
    filters: { per_page?: string; academic_year_id?: string };
    perPageOptions: number[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    selectedAcademicYearId: number;
    tingkatSekolahs: TingkatSekolahFormal[];
    copySourceAcademicYear?: Pick<AcademicYear, 'id' | 'name'> | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Data Master', href: '/admin/students' },
    { title: 'Enroll Tingkat Formal', href: '/admin/formal-tingkat/assign' },
];

export default function FormalTingkatAssign({
    unassignedStudents,
    filters,
    perPageOptions,
    academicYears,
    selectedAcademicYearId,
    tingkatSekolahs,
    copySourceAcademicYear,
}: Props) {
    const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
    const [copyProcessing, setCopyProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

    const assignForm = useForm<{ student_ids: number[]; tingkat_sekolah_id: string }>({
        student_ids: [],
        tingkat_sekolah_id: '',
    });

    const currentAcademicYearId = Number(filters.academic_year_id ?? String(selectedAcademicYearId));

    const importForm = useForm<{ file: File | null; enrollment_strategy: 'skip' | 'replace' }>({
        file: null,
        enrollment_strategy: 'skip',
    });

    function setPerPage(value: string) {
        router.get('/admin/formal-tingkat/assign', { per_page: value, academic_year_id: currentAcademicYearId }, { preserveState: true, preserveScroll: true });
    }

    function setAcademicYear(value: string) {
        router.get(
            '/admin/formal-tingkat/assign',
            { per_page: filters.per_page ?? perPageOptions[0], academic_year_id: value },
            { preserveState: true, preserveScroll: true },
        );
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
        if (!assignForm.data.tingkat_sekolah_id) {
            toast.error('Pilih tingkat sekolah formal');
            return;
        }
        assignForm.setData('student_ids', selectedStudents);
        assignForm.transform((data) => ({
            ...data,
            academic_year_id: currentAcademicYearId,
        }));
        assignForm.post('/admin/formal-tingkat/enrollments', {
            onSuccess: () => {
                setSelectedStudents([]);
                assignForm.setData('student_ids', []);
                toast.success('Enrollment tingkat formal tersimpan');
            },
            onError: () => toast.error('Gagal menyimpan enrollment'),
        });
    }

    function submitImport(e: React.FormEvent) {
        e.preventDefault();
        importForm.post('/admin/formal-tingkat/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
            },
            onError: () => toast.error('Gagal mengimpor file'),
        });
    }

    function copyFromPreviousAcademicYear() {
        if (!copySourceAcademicYear) return;
        const ok = window.confirm(
            `Salin enrollment tingkat formal dari ${copySourceAcademicYear.name} ke tahun ajaran terpilih? Santri yang sudah punya data di TA tujuan akan di-skip.`,
        );
        if (!ok) return;

        setCopyProcessing(true);
        router.post(
            '/admin/formal-tingkat/enrollments/copy-from-year',
            {
                source_academic_year_id: copySourceAcademicYear.id,
                target_academic_year_id: currentAcademicYearId,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Copy enrollment selesai'),
                onError: () => toast.error('Gagal menyalin enrollment'),
                onFinish: () => setCopyProcessing(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enroll Tingkat Formal" />
            <div>
                <CrudPageHeader
                    title="Enrollment tingkat sekolah formal"
                    description="Tetapkan MTs / MA / kuliah (acuan invoice & laporan keuangan) per santri dan tahun ajaran. Berbeda dari kelas diniyyah."
                />
                <CrudStatStrip
                    items={[
                        {
                            key: 'unassigned',
                            label: 'Belum ada tingkat (TA ini)',
                            value: unassignedStudents.total,
                            icon: <Users size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'tingkat',
                            label: 'Pilihan tingkat',
                            value: tingkatSekolahs.length,
                            icon: <Layers size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'selected',
                            label: 'Terpilih',
                            value: selectedStudents.length,
                            icon: <CheckCircle2 size={18} />,
                            tone: 'purple',
                        },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <select
                                className="mcr-filter-select"
                                value={filters.per_page ?? String(perPageOptions[0] ?? 25)}
                                onChange={(e) => setPerPage(e.target.value)}
                            >
                                {perPageOptions.map((opt) => (
                                    <option key={opt} value={String(opt)}>
                                        {opt} / halaman
                                    </option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={String(currentAcademicYearId)} onChange={(e) => setAcademicYear(e.target.value)}>
                                {academicYears.map((year) => (
                                    <option key={year.id} value={String(year.id)}>
                                        {year.name}
                                    </option>
                                ))}
                            </select>
                            <span className="mcr-table-meta">Daftar menampilkan santri aktif yang belum punya enrollment tingkat formal untuk TA terpilih.</span>
                        </>
                    }
                    right={
                        <>
                            <button
                                type="button"
                                className="mcr-btn ghost"
                                onClick={() => openDownload('/admin/formal-tingkat/template?format=xlsx')}
                                title=".xlsx: sheet Referensi_Tingkat dan Enrollment"
                            >
                                <FileText size={14} />
                                Template (.xlsx)
                            </button>
                            <button
                                type="button"
                                className="mcr-btn ghost"
                                onClick={() => openDownload('/admin/formal-tingkat/export/master?format=xlsx')}
                                title="Master tingkat formal saat ini"
                            >
                                <Download size={14} />
                                Export master tingkat
                            </button>
                            <button
                                type="button"
                                className="mcr-btn ghost"
                                onClick={() =>
                                    openDownload(`/admin/formal-tingkat/export/enrollments?academic_year_id=${currentAcademicYearId}&format=xlsx`)
                                }
                            >
                                <Download size={14} />
                                Export enrollment TA
                            </button>
                            <button type="button" className="mcr-btn secondary" onClick={() => setImportOpen(true)}>
                                <FileUp size={14} />
                                Impor Excel
                            </button>
                        </>
                    }
                />

                <CrudCard title="Santri belum enrollment tingkat formal (TA ini)">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <input
                                            type="checkbox"
                                            className="mcr-check"
                                            checked={
                                                unassignedStudents.data.length > 0 &&
                                                unassignedStudents.data.every((s) => selectedStudents.includes(s.id))
                                            }
                                            onChange={toggleAllStudents}
                                        />
                                    </th>
                                    <th>NIS</th>
                                    <th>Nama Santri</th>
                                </tr>
                            </thead>
                            <tbody>
                                {unassignedStudents.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={3}>
                                            <CrudEmptyState
                                                title="Tidak ada data"
                                                description="Semua santri aktif sudah memiliki enrollment tingkat formal untuk tahun ajaran ini, atau tidak ada santri aktif."
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    unassignedStudents.data.map((student) => (
                                        <tr key={student.id}>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    className="mcr-check"
                                                    checked={selectedStudents.includes(student.id)}
                                                    onChange={() => toggleStudent(student.id)}
                                                />
                                            </td>
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

                <CrudCard title="Form assignment" subtitle="Satu santri hanya satu baris per tahun ajaran (update jika sudah ada).">
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="formal-tingkat-select">Tingkat sekolah formal</label>
                            <select
                                id="formal-tingkat-select"
                                className="mcr-form-select"
                                value={assignForm.data.tingkat_sekolah_id}
                                onChange={(e) => assignForm.setData('tingkat_sekolah_id', e.target.value)}
                            >
                                <option value="">Pilih tingkat</option>
                                {tingkatSekolahs.map((t) => (
                                    <option key={t.id} value={String(t.id)}>
                                        {[t.group, t.name, t.code ? `(${t.code})` : ''].filter(Boolean).join(' — ')}
                                    </option>
                                ))}
                            </select>
                            <InputError message={assignForm.errors.tingkat_sekolah_id} />
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
                            {assignForm.processing ? 'Memproses...' : 'Simpan enrollment'}
                        </button>
                    </CrudBulkActionBar>
                </CrudCard>
            </div>

            <CrudModal
                open={importOpen}
                onClose={() => setImportOpen(false)}
                title="Impor Excel — tingkat formal"
                subtitle="Workbook minimal berisi dua sheet dengan nama persis seperti di template (.xlsx)."
            >
                <form onSubmit={submitImport}>
                    <div
                        style={{
                            marginBottom: 16,
                            padding: '10px 12px',
                            background: 'var(--mcr-muted-bg, #f8fafc)',
                            borderRadius: 8,
                            fontSize: 13,
                            lineHeight: 1.5,
                            color: 'var(--mcr-text-muted, #64748b)',
                        }}
                    >
                        <strong style={{ color: 'var(--mcr-text, #0f172a)' }}>Sheet wajib</strong>
                        <ul style={{ margin: '8px 0 0 18px', padding: 0 }}>
                            <li>
                                <code>Referensi_Tingkat</code> — dokumentasi kode tingkat (impor mengabaikan isinya; gunakan untuk referensi manual).
                            </li>
                            <li>
                                <code>Enrollment</code> — wajib: <code>nis</code>, plus salah satu pasangan tahun ajaran{' '}
                                <code>academic_year_name</code> atau <code>academic_year_id</code>, dan tingkat{' '}
                                <code>tingkat_code</code> atau <code>tingkat_sekolah_id</code>. Baris tanpa NIS dilewati.
                            </li>
                        </ul>
                        <p style={{ margin: '8px 0 0' }}>
                            <strong>Strategi enrollment</strong> memengaruhi baris jika santri sudah punya enrollment di tahun ajaran pada baris tersebut: lewati vs
                            ganti <code>tingkat_sekolah_id</code>.
                        </p>
                    </div>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full">
                            <label htmlFor="formal-tingkat-import-file">File (.xlsx / .xls)</label>
                            <input
                                id="formal-tingkat-import-file"
                                className="mcr-input"
                                type="file"
                                accept=".xlsx,.xls"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            />
                            <InputError message={importForm.errors.file} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="formal-tingkat-enrollment-strategy">Strategi enrollment (sheet Enrollment)</label>
                            <select
                                id="formal-tingkat-enrollment-strategy"
                                className="mcr-form-select"
                                value={importForm.data.enrollment_strategy}
                                onChange={(e) => importForm.setData('enrollment_strategy', e.target.value as 'skip' | 'replace')}
                            >
                                <option value="skip">Lewati jika sudah ada enrollment di TA baris</option>
                                <option value="replace">Perbarui tingkat jika sudah ada enrollment di TA baris</option>
                            </select>
                            <InputError message={importForm.errors.enrollment_strategy} />
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
