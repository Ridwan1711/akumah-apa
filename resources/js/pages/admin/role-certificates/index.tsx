import { Head, router, useForm } from '@inertiajs/react';
import { Download, Plus, RotateCcw, Search, ScrollText } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import {
    AppSelect,
    type SelectOption,
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
import type { BreadcrumbItem, PaginatedData } from '@/types';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Surat Keterangan', href: '/admin/role-certificates' },
];

type CertificateRow = {
    id: number;
    certificate_number: string;
    certificate_type: 'teacher' | 'student_position';
    issuance_mode: 'auto' | 'manual';
    status: string;
    issued_at: string | null;
    user?: { id: number; name: string } | null;
    student_position?: {
        id: number;
        position_type: string;
        division_code: string | null;
        student?: { id: number; full_name: string } | null;
    } | null;
    period?: { id: number; academic_year?: { id: number; name: string } | null } | null;
};

type Props = {
    certificates: PaginatedData<CertificateRow>;
    teachers: { id: number; name: string }[];
    positions: { id: number; position_type: string; division_code: string | null; student?: { id: number; full_name: string } | null }[];
    periods: { id: number; academic_year?: { id: number; name: string } | null }[];
    filters: {
        search?: string;
        certificate_type?: string;
        status?: string;
    };
};

type ManualIssueForm = {
    certificate_type: 'teacher' | 'student_position';
    user_id: string;
    student_position_id: string;
    academic_period_id: string;
    valid_from: string;
    valid_until: string;
    notes: string;
};

const initialIssueForm: ManualIssueForm = {
    certificate_type: 'teacher',
    user_id: '',
    student_position_id: '',
    academic_period_id: '',
    valid_from: '',
    valid_until: '',
    notes: '',
};

export default function RoleCertificateIndex({ certificates, teachers, positions, periods, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    const [typeFilter, setTypeFilter] = useState(filters.certificate_type ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [issueOpen, setIssueOpen] = useState(false);
    const issueForm = useForm<ManualIssueForm>(initialIssueForm);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedSearch(search), 300);
        return () => window.clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        router.get(
            '/admin/role-certificates',
            {
                search: debouncedSearch || undefined,
                certificate_type: typeFilter || undefined,
                status: statusFilter || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [debouncedSearch, typeFilter, statusFilter]);

    const teacherCount = useMemo(
        () => certificates.data.filter((item) => item.certificate_type === 'teacher').length,
        [certificates.data],
    );
    const positionCount = Math.max(0, certificates.data.length - teacherCount);
    const typeFilterOptions = useMemo<SelectOption[]>(
        () => [
            { value: 'teacher', label: 'Guru' },
            { value: 'student_position', label: 'Pengurus' },
        ],
        [],
    );
    const statusFilterOptions = useMemo<SelectOption[]>(
        () => [
            { value: 'issued', label: 'Issued' },
            { value: 'reissued', label: 'Reissued' },
            { value: 'archived', label: 'Archived' },
        ],
        [],
    );
    const certificateTypeOptions = useMemo<SelectOption[]>(
        () => [
            { value: 'teacher', label: 'Guru' },
            { value: 'student_position', label: 'Pengurus' },
        ],
        [],
    );
    const teacherOptions = useMemo<SelectOption[]>(
        () => teachers.map((teacher) => ({ value: String(teacher.id), label: teacher.name })),
        [teachers],
    );
    const positionOptions = useMemo<SelectOption[]>(
        () =>
            positions.map((position) => ({
                value: String(position.id),
                label: `${position.student?.full_name ?? '-'} - ${position.position_type}`,
            })),
        [positions],
    );
    const periodOptions = useMemo<SelectOption[]>(
        () =>
            periods.map((period) => ({
                value: String(period.id),
                label: period.academic_year?.name ?? `Periode #${period.id}`,
            })),
        [periods],
    );

    function submitIssueForm(e: React.FormEvent) {
        e.preventDefault();
        const payload = {
            ...issueForm.data,
            user_id: issueForm.data.user_id || null,
            student_position_id: issueForm.data.student_position_id || null,
            academic_period_id: issueForm.data.academic_period_id || null,
            valid_from: issueForm.data.valid_from || null,
            valid_until: issueForm.data.valid_until || null,
            action: 'reissue',
        };

        issueForm.transform(() => payload);
        issueForm.post('/admin/role-certificates', {
            preserveScroll: true,
            onSuccess: () => {
                setIssueOpen(false);
                issueForm.setData(initialIssueForm);
                toast.success('Surat keterangan berhasil diterbitkan.');
            },
            onError: () => toast.error('Gagal menerbitkan surat keterangan.'),
        });
    }

    function resetFilters() {
        setSearch('');
        setTypeFilter('');
        setStatusFilter('');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Surat Keterangan" />
            <div>
                <CrudPageHeader
                    title="Surat Keterangan Guru/Pengurus"
                    description="Kelola surat keterangan otomatis maupun regenerate manual."
                />

                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Surat', value: certificates.total, icon: <ScrollText size={18} />, tone: 'blue' },
                        { key: 'teacher', label: 'Surat Guru (halaman)', value: teacherCount, icon: <ScrollText size={18} />, tone: 'green' },
                        { key: 'position', label: 'Surat Pengurus (halaman)', value: positionCount, icon: <ScrollText size={18} />, tone: 'amber' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nomor surat / nama..."
                                />
                            </div>
                            <div style={{ minWidth: 220 }}>
                                <AppSelect
                                    placeholder="Filter tipe..."
                                    options={typeFilterOptions}
                                    value={typeFilterOptions.find((item) => item.value === typeFilter) ?? null}
                                    onChange={(option) => setTypeFilter(String(option?.value ?? ''))}
                                />
                            </div>
                            <div style={{ minWidth: 220 }}>
                                <AppSelect
                                    placeholder="Filter status..."
                                    options={statusFilterOptions}
                                    value={statusFilterOptions.find((item) => item.value === statusFilter) ?? null}
                                    onChange={(option) => setStatusFilter(String(option?.value ?? ''))}
                                />
                            </div>
                            {(search || typeFilter || statusFilter) && (
                                <button type="button" className="mcr-btn ghost" onClick={resetFilters}>
                                    <RotateCcw size={14} />
                                    Reset
                                </button>
                            )}
                        </>
                    }
                    right={
                        <button type="button" className="mcr-btn primary" onClick={() => setIssueOpen(true)}>
                            <Plus size={14} />
                            Regenerate Manual
                        </button>
                    }
                />

                <CrudTableShell>
                    <table className="mcr-table">
                        <thead>
                            <tr>
                                <th>Nomor Surat</th>
                                <th>Tipe</th>
                                <th>Entitas</th>
                                <th>Tahun Ajaran</th>
                                <th>Mode/Status</th>
                                <th style={{ textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {certificates.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <CrudEmptyState
                                            title="Belum ada surat keterangan"
                                            description="Surat akan otomatis terbit saat penunjukan Guru/Pengurus."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                certificates.data.map((certificate) => (
                                    <tr key={certificate.id}>
                                        <td>{certificate.certificate_number}</td>
                                        <td>{certificate.certificate_type === 'teacher' ? 'Guru' : 'Pengurus'}</td>
                                        <td>
                                            {certificate.certificate_type === 'teacher'
                                                ? (certificate.user?.name ?? '-')
                                                : (certificate.student_position?.student?.full_name ?? '-')}
                                        </td>
                                        <td>{certificate.period?.academic_year?.name ?? '-'}</td>
                                        <td>
                                            <span className="mcr-dot-badge alumni">{certificate.issuance_mode}</span>{' '}
                                            <span className="mcr-dot-badge active">{certificate.status}</span>
                                        </td>
                                        <td>
                                            <div className="mcr-action-group">
                                                <a
                                                    href={`/admin/role-certificates/${certificate.id}/download`}
                                                    className="mcr-icon-action"
                                                    title="Unduh PDF"
                                                >
                                                    <Download size={13} />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CrudTableShell>

                <CrudPagination links={certificates.links} />
            </div>

            <CrudModal
                open={issueOpen}
                onClose={() => setIssueOpen(false)}
                title="Regenerate Surat Keterangan"
                subtitle="Terbitkan ulang surat secara manual dengan periode default atau tanggal custom."
            >
                <form onSubmit={submitIssueForm}>
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <label htmlFor="certificate-type">Tipe Surat</label>
                            <AppSelect
                                inputId="certificate-type"
                                options={certificateTypeOptions}
                                value={certificateTypeOptions.find((item) => item.value === issueForm.data.certificate_type) ?? null}
                                onChange={(option) => issueForm.setData('certificate_type', (option?.value as 'teacher' | 'student_position') ?? 'teacher')}
                            />
                            <InputError message={issueForm.errors.certificate_type} />
                        </div>
                        {issueForm.data.certificate_type === 'teacher' ? (
                            <div className="mcr-form-group">
                                <label htmlFor="certificate-teacher">Guru</label>
                                <AppSelect
                                    inputId="certificate-teacher"
                                    placeholder="Pilih guru..."
                                    options={teacherOptions}
                                    value={teacherOptions.find((item) => item.value === issueForm.data.user_id) ?? null}
                                    onChange={(option) => issueForm.setData('user_id', String(option?.value ?? ''))}
                                />
                                <InputError message={issueForm.errors.user_id} />
                            </div>
                        ) : (
                            <div className="mcr-form-group">
                                <label htmlFor="certificate-position">Pengurus</label>
                                <AppSelect
                                    inputId="certificate-position"
                                    placeholder="Pilih pengurus..."
                                    options={positionOptions}
                                    value={positionOptions.find((item) => item.value === issueForm.data.student_position_id) ?? null}
                                    onChange={(option) => issueForm.setData('student_position_id', String(option?.value ?? ''))}
                                />
                                <InputError message={issueForm.errors.student_position_id} />
                            </div>
                        )}
                        <div className="mcr-form-group">
                            <label htmlFor="certificate-period">Periode Akademik (default)</label>
                            <AppSelect
                                inputId="certificate-period"
                                placeholder="Gunakan periode aktif"
                                options={periodOptions}
                                value={periodOptions.find((item) => item.value === issueForm.data.academic_period_id) ?? null}
                                onChange={(option) => issueForm.setData('academic_period_id', String(option?.value ?? ''))}
                            />
                            <InputError message={issueForm.errors.academic_period_id} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="certificate-valid-from">Valid Dari (custom)</label>
                            <input
                                id="certificate-valid-from"
                                type="date"
                                className="mcr-input"
                                value={issueForm.data.valid_from}
                                onChange={(e) => issueForm.setData('valid_from', e.target.value)}
                            />
                            <InputError message={issueForm.errors.valid_from} />
                        </div>
                        <div className="mcr-form-group">
                            <label htmlFor="certificate-valid-until">Valid Sampai (custom)</label>
                            <input
                                id="certificate-valid-until"
                                type="date"
                                className="mcr-input"
                                value={issueForm.data.valid_until}
                                onChange={(e) => issueForm.setData('valid_until', e.target.value)}
                            />
                            <InputError message={issueForm.errors.valid_until} />
                        </div>
                        <div className="mcr-form-group full">
                            <label htmlFor="certificate-notes">Catatan</label>
                            <textarea
                                id="certificate-notes"
                                className="mcr-input"
                                value={issueForm.data.notes}
                                onChange={(e) => issueForm.setData('notes', e.target.value)}
                            />
                            <InputError message={issueForm.errors.notes} />
                        </div>
                    </div>
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button type="button" className="mcr-btn ghost" onClick={() => setIssueOpen(false)}>
                            Batal
                        </button>
                        <button type="submit" className="mcr-btn primary" disabled={issueForm.processing}>
                            {issueForm.processing ? 'Memproses...' : 'Terbitkan'}
                        </button>
                    </div>
                </form>
            </CrudModal>
        </AppLayout>
    );
}
