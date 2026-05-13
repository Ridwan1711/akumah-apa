import { Head, router, useForm } from '@inertiajs/react';
import { BookOpenCheck, Layers, Trash2, UserCheck, Users } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    AppSelect,
    CrudCard,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
    type SelectOption,
} from '@/components/manhood';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Penguji Baca Kitab', href: '/admin/kitab-reading-examiners' },
];

type SemesterOption = {
    id: number;
    name: string;
    academic_year_name?: string | null;
    is_active?: boolean;
};

type Assignment = {
    id: number;
    examiner?: Pick<User, 'id' | 'name'>;
    school_class?: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>;
    period?: {
        id: number;
        academic_year?: { id: number; name: string };
        semester?: { id: number; name: string };
    };
};

type Props = {
    assignments: Assignment[];
    examiners: Pick<User, 'id' | 'name'>[];
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id' | 'student_gender'>[];
    semesters: SemesterOption[];
    selectedSemesterId: number | null;
};

export default function KitabReadingExaminerIndex({ assignments, examiners, classes, semesters, selectedSemesterId }: Props) {
    const selectedSemester = selectedSemesterId ? String(selectedSemesterId) : '';
    const form = useForm({
        examiner_id: '',
        class_id: '',
        semester_id: selectedSemester,
    });

    useEffect(() => {
        form.setData('semester_id', selectedSemester);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedSemester]);

    const semesterOptions = useMemo<SelectOption[]>(
        () =>
            semesters.map((s) => ({
                value: s.id,
                label: `${s.academic_year_name ?? '—'} — ${s.name}${s.is_active ? ' (Aktif)' : ''}`,
            })),
        [semesters],
    );

    const classOptions = useMemo<SelectOption[]>(
        () => classes.map((c) => ({ value: c.id, label: c.name })),
        [classes],
    );

    const examinerOptions = useMemo<SelectOption[]>(
        () => examiners.map((u) => ({ value: u.id, label: u.name })),
        [examiners],
    );

    const selectedSemesterOption = useMemo(
        () => semesterOptions.find((o) => String(o.value) === form.data.semester_id) ?? null,
        [semesterOptions, form.data.semester_id],
    );

    const selectedClassOption = useMemo(
        () => classOptions.find((o) => String(o.value) === form.data.class_id) ?? null,
        [classOptions, form.data.class_id],
    );

    const selectedExaminerOption = useMemo(
        () => examinerOptions.find((o) => String(o.value) === form.data.examiner_id) ?? null,
        [examinerOptions, form.data.examiner_id],
    );

    function changeSemester(option: SelectOption | null) {
        const value = option ? String(option.value) : '';
        form.setData('semester_id', value);
        if (!value) {
            router.get('/admin/kitab-reading-examiners', {}, { preserveState: true, preserveScroll: true, replace: true });
            return;
        }
        router.get('/admin/kitab-reading-examiners', { semester_id: value }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/kitab-reading-examiners', {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({
                    examiner_id: '',
                    class_id: '',
                    semester_id: form.data.semester_id,
                });
            },
        });
    }

    function destroyAssignment(id: number) {
        router.delete(`/admin/kitab-reading-examiners/${id}`, {
            data: { semester_id: form.data.semester_id },
            preserveScroll: true,
        });
    }

    const canSubmit = Boolean(form.data.semester_id && form.data.class_id && form.data.examiner_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penguji Baca Kitab" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudPageHeader
                    title="Penguji Baca Kitab"
                    description="Tetapkan penguji per kelas & semester. Gunakan kotak pencarian di dropdown untuk memfilter daftar panjang."
                />
                <FlashMessage />

                <CrudStatStrip
                    items={[
                        {
                            key: 'sem',
                            label: 'Semester dipilih',
                            value: selectedSemesterOption?.label ?? '—',
                            icon: <BookOpenCheck size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'assign',
                            label: 'Penugasan (filter)',
                            value: assignments.length,
                            icon: <UserCheck size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'avail',
                            label: 'Kelas belum ditugaskan',
                            value: classes.length,
                            icon: <Layers size={18} />,
                            tone: 'amber',
                        },
                        {
                            key: 'exam',
                            label: 'Kandidat penguji',
                            value: examiners.length,
                            icon: <Users size={18} />,
                            tone: 'purple',
                        },
                    ]}
                />

                <CrudCard
                    title="1. Pilih semester"
                    subtitle="Ini mem-filter tabel penugasan di bawah. Ketik di dropdown untuk mencari tahun ajaran atau nama semester."
                    right={
                        selectedSemesterOption ? (
                            <Badge variant="outline" className="shrink-0">
                                Filter aktif
                            </Badge>
                        ) : null
                    }
                >
                    <div className="mcr-form-grid">
                        <div className="mcr-form-group full" style={{ maxWidth: 520 }}>
                            <Label htmlFor="kre-semester">Semester</Label>
                            <AppSelect
                                inputId="kre-semester"
                                placeholder="Cari semester…"
                                options={semesterOptions}
                                value={selectedSemesterOption}
                                onChange={(opt) => changeSemester(opt)}
                                isClearable
                            />
                        </div>
                    </div>
                </CrudCard>

                <CrudCard
                    title="2. Tambah penugasan"
                    subtitle="Pilih kelas yang masih tersedia lalu penguji. Dropdown mendukung pencarian."
                >
                    <form onSubmit={submit} className="mcr-form-grid">
                        <div className="mcr-form-group">
                            <Label htmlFor="kre-class">Kelas</Label>
                            <AppSelect
                                inputId="kre-class"
                                placeholder="Cari kelas…"
                                options={classOptions}
                                value={selectedClassOption}
                                onChange={(opt) => form.setData('class_id', opt ? String(opt.value) : '')}
                                isDisabled={classOptions.length === 0}
                                noOptionsMessage={() => 'Tidak ada kelas tersedia (semua sudah punya penugasan global).'}
                            />
                        </div>
                        <div className="mcr-form-group">
                            <Label htmlFor="kre-examiner">Penguji</Label>
                            <AppSelect
                                inputId="kre-examiner"
                                placeholder="Cari nama penguji…"
                                options={examinerOptions}
                                value={selectedExaminerOption}
                                onChange={(opt) => form.setData('examiner_id', opt ? String(opt.value) : '')}
                                isDisabled={examinerOptions.length === 0}
                            />
                        </div>
                        <div className="mcr-form-group full" style={{ display: 'flex', alignItems: 'flex-end', gap: 10, flexWrap: 'wrap' }}>
                            <Button type="submit" disabled={form.processing || !canSubmit} className="mcr-btn primary">
                                <UserCheck className="mr-1 size-4" />
                                {form.processing ? 'Menyimpan…' : 'Tugaskan'}
                            </Button>
                            {!canSubmit ? (
                                <span className="text-xs text-muted-foreground">Lengkapi semester, kelas, dan penguji untuk mengaktifkan tombol.</span>
                            ) : null}
                        </div>
                        <p className="mcr-form-group full text-xs text-muted-foreground" style={{ marginBottom: 0 }}>
                            Penguji tidak harus guru mapel. Akun santri, wali santri, dan alumni tidak bisa dipilih.
                        </p>
                    </form>
                </CrudCard>

                <CrudCard title="Daftar penugasan" subtitle="Menampilkan penugasan untuk semester yang dipilih di atas.">
                    <CrudTableShell>
                        <table className="mcr-table">
                            <thead>
                                <tr>
                                    <th style={{ width: 48 }}>No</th>
                                    <th>Penguji</th>
                                    <th>Kelas</th>
                                    <th>Periode</th>
                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {assignments.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-3 py-10 text-center text-muted-foreground">
                                            <BookOpenCheck className="mx-auto mb-2 size-8 opacity-60" />
                                            Belum ada penguji baca kitab pada semester ini.
                                        </td>
                                    </tr>
                                ) : (
                                    assignments.map((assignment, index) => (
                                        <tr key={assignment.id}>
                                            <td className="text-muted-foreground">{index + 1}</td>
                                            <td className="font-medium">{assignment.examiner?.name}</td>
                                            <td>{assignment.school_class?.name}</td>
                                            <td>
                                                <Badge variant="outline">
                                                    {assignment.period?.academic_year?.name} — {assignment.period?.semester?.name}
                                                </Badge>
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button
                                                    type="button"
                                                    className="mcr-btn danger"
                                                    onClick={() => destroyAssignment(assignment.id)}
                                                >
                                                    <Trash2 size={14} />
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CrudTableShell>
                </CrudCard>
            </div>
        </AppLayout>
    );
}
