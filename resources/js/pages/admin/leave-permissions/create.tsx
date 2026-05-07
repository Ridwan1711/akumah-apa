import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { AppSelect, CrudCard, CrudPageHeader, CrudToolbar  } from '@/components/manhood';
import type {SelectOption} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

type Props = {
    students: Pick<Student, 'id' | 'nis' | 'full_name'>[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Perizinan', href: '/admin/leave-permissions' },
    { title: 'Ajukan Izin', href: '/admin/leave-permissions/create' },
];

export default function LeavePermissionCreate({ students }: Props) {
    const form = useForm({
        student_id: '',
        reason: '',
        leave_date: '',
        return_date: '',
    });

    const studentOptions = useMemo<SelectOption[]>(
        () =>
            students.map((student) => ({
                value: String(student.id),
                label: `${student.full_name} (${student.nis})`,
            })),
        [students],
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/leave-permissions');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajukan Izin" />
            <div>
                <CrudPageHeader
                    title="Ajukan Izin Santri"
                    description="Halaman langsung untuk membuat pengajuan izin, selain modal di daftar perizinan."
                />

                <CrudToolbar
                    left={null}
                    right={
                        <Link href="/admin/leave-permissions" className="mcr-btn ghost">
                            <ArrowLeft size={14} />
                            Kembali
                        </Link>
                    }
                />

                <CrudCard title="Form Pengajuan Izin">
                    <form className="mcr-form-grid" onSubmit={submit}>
                        <div className="mcr-form-group full">
                            <label htmlFor="student_id">Santri</label>
                            <AppSelect
                                inputId="student_id"
                                options={studentOptions}
                                value={studentOptions.find((option) => option.value === form.data.student_id) ?? null}
                                onChange={(option) => form.setData('student_id', String(option?.value ?? ''))}
                                placeholder="Pilih santri"
                            />
                            <InputError message={form.errors.student_id} />
                        </div>

                        <div className="mcr-form-group">
                            <label htmlFor="leave_date">Tanggal Izin</label>
                            <input
                                id="leave_date"
                                className="mcr-form-date"
                                type="date"
                                value={form.data.leave_date}
                                onChange={(e) => form.setData('leave_date', e.target.value)}
                            />
                            <InputError message={form.errors.leave_date} />
                        </div>

                        <div className="mcr-form-group">
                            <label htmlFor="return_date">Rencana Kembali</label>
                            <input
                                id="return_date"
                                className="mcr-form-date"
                                type="date"
                                value={form.data.return_date}
                                onChange={(e) => form.setData('return_date', e.target.value)}
                            />
                            <InputError message={form.errors.return_date} />
                        </div>

                        <div className="mcr-form-group full">
                            <label htmlFor="reason">Alasan</label>
                            <textarea
                                id="reason"
                                className="mcr-textarea"
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                            <InputError message={form.errors.reason} />
                        </div>

                        <div className="mcr-form-group full" style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                            <Link href="/admin/leave-permissions" className="mcr-btn ghost">
                                Batal
                            </Link>
                            <button type="submit" className="mcr-btn primary" disabled={form.processing}>
                                <Save size={14} />
                                {form.processing ? 'Menyimpan...' : 'Simpan Pengajuan'}
                            </button>
                        </div>
                    </form>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
