import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Save, User } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import type { EmProfileFormData } from '@/components/student-profile';
import {
    EmProfileForm,
    ProfileSectionCard,
    emProfileDefaults,
} from '@/components/student-profile';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

type Props = {
    student: Student;
};

function normalizeDateForInput(value?: string | null): string {
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    if (value.includes('T')) {
        const [datePart] = value.split('T');
        if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return datePart;
    }
    return '';
}

function Field({
    label,
    required,
    span2,
    children,
}: {
    label: string;
    required?: boolean;
    span2?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className={`flex flex-col gap-1.5 ${span2 ? 'md:col-span-2' : ''}`}>
            <label className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                {label}
                {required && <span className="ml-1 text-rose-500">*</span>}
            </label>
            {children}
        </div>
    );
}

function StyledInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <Input
            {...props}
            className="h-11 rounded-xl border-border bg-white text-sm text-foreground shadow-sm transition-all placeholder:text-muted-foreground focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
        />
    );
}

export default function WaliChildEdit({ student }: Props) {
    const ep = student.emis_profile;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Anak Saya', href: '/wali/children' },
        { title: student.full_name, href: `/wali/children/${student.id}` },
        { title: 'Edit Profil', href: `/wali/children/${student.id}/edit` },
    ];

    const form = useForm<{
        full_name: string;
        nik: string;
        birth_place: string;
        birth_date: string;
        gender: 'L' | 'P';
        address: string;
        em_profile: EmProfileFormData;
    }>({
        full_name: student.full_name ?? '',
        nik: student.nik ?? '',
        birth_place: student.birth_place ?? '',
        birth_date: normalizeDateForInput(student.birth_date),
        gender: student.gender ?? 'L',
        address: student.address ?? '',
        em_profile: emProfileDefaults(ep),
    });

    function handleEmProfileChange(field: keyof EmProfileFormData, value: string) {
        form.setData('em_profile', { ...form.data.em_profile, [field]: value });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/wali/children/${student.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Profil — ${student.full_name}`} />

            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">

                    {/* Header */}
                    <div className="mb-8">
                        <Link
                            href={`/wali/children/${student.id}`}
                            className="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-muted-foreground shadow-sm border border-border transition-all hover:bg-muted/40 hover:text-foreground active:scale-[0.98]"
                        >
                            <ArrowLeft size={16} />
                            Kembali ke Detail Anak
                        </Link>

                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-sidebar text-white shadow-lg">
                                <BookOpen size={24} strokeWidth={1.5} />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold leading-none text-foreground sm:text-4xl">
                                    Edit Profil Anak
                                </h1>
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    Perbarui data diri dan data EMIS{' '}
                                    <span className="font-semibold text-foreground">{student.full_name}</span>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <FlashMessage />

                    {/* Mini hero */}
                    <div className="mb-6 overflow-hidden rounded-3xl border border-emerald-200/40 bg-linear-to-b from-[#a9b89f] to-[#ccd6c4] p-6 text-foreground shadow-sm">
                        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full border-2 border-white/70 bg-card/70 text-2xl font-bold text-emerald-800">
                                    {(student.full_name?.charAt(0) || '?').toUpperCase()}
                                </div>
                                <div>
                                    <h2 className="text-3xl font-bold leading-tight text-white">{student.full_name}</h2>
                                    <span className="mt-1 inline-flex rounded-full bg-white/60 px-3 py-1 text-xs font-semibold text-emerald-800">
                                        NIS: {student.nis}
                                    </span>
                                </div>
                            </div>
                            <Link
                                href={`/wali/children/${student.id}`}
                                className="inline-flex items-center gap-2 rounded-xl border border-white/80 bg-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/30"
                            >
                                <ArrowLeft size={16} />
                                Batal
                            </Link>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Data Pribadi */}
                        <ProfileSectionCard
                            icon={User}
                            title="Data Pribadi"
                            subtitle="Informasi dasar santri"
                        >
                            <div className="py-6 grid gap-5 sm:grid-cols-2">
                                <Field label="Nama Lengkap" required span2>
                                    <StyledInput
                                        value={form.data.full_name}
                                        onChange={(e) => form.setData('full_name', e.target.value)}
                                        placeholder="Sesuai KTP/Akta"
                                    />
                                    <InputError message={form.errors.full_name} />
                                </Field>

                                <Field label="NIK">
                                    <StyledInput
                                        value={form.data.nik}
                                        onChange={(e) => form.setData('nik', e.target.value)}
                                        placeholder="16 digit NIK"
                                        maxLength={16}
                                        className="h-11 rounded-xl border-border bg-white text-sm font-mono tracking-widest shadow-sm transition-all placeholder:tracking-normal placeholder:font-sans placeholder:text-muted-foreground focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                    />
                                    <InputError message={form.errors.nik} />
                                </Field>

                                <Field label="Jenis Kelamin" required>
                                    <Select
                                        value={form.data.gender}
                                        onValueChange={(v) => form.setData('gender', v as 'L' | 'P')}
                                    >
                                        <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                            <SelectValue placeholder="Pilih jenis kelamin" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="L">Laki-laki</SelectItem>
                                            <SelectItem value="P">Perempuan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.gender} />
                                </Field>

                                <Field label="Tempat Lahir">
                                    <StyledInput
                                        value={form.data.birth_place}
                                        onChange={(e) => form.setData('birth_place', e.target.value)}
                                        placeholder="misal: Surabaya"
                                    />
                                    <InputError message={form.errors.birth_place} />
                                </Field>

                                <Field label="Tanggal Lahir">
                                    <StyledInput
                                        type="date"
                                        value={form.data.birth_date}
                                        onChange={(e) => form.setData('birth_date', e.target.value)}
                                    />
                                    <InputError message={form.errors.birth_date} />
                                </Field>

                                <Field label="Alamat" span2>
                                    <textarea
                                        value={form.data.address}
                                        onChange={(e) => form.setData('address', e.target.value)}
                                        rows={3}
                                        placeholder="Alamat tempat tinggal saat ini"
                                        className="w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-foreground shadow-sm placeholder:text-muted-foreground transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                    <InputError message={form.errors.address} />
                                </Field>
                            </div>
                        </ProfileSectionCard>

                        {/* Data EMIS */}
                        <EmProfileForm
                            data={form.data.em_profile}
                            errors={
                                Object.fromEntries(
                                    Object.entries(form.errors)
                                        .filter(([k]) => k.startsWith('em_profile.'))
                                        .map(([k, v]) => [k.replace('em_profile.', ''), v]),
                                ) as Partial<Record<keyof EmProfileFormData, string>>
                            }
                            onChange={handleEmProfileChange}
                            showCatatan={false}
                            nismReadOnly
                        />

                        {/* Submit */}
                        <div className="flex justify-end gap-3 pb-4">
                            <Link
                                href={`/wali/children/${student.id}`}
                                className="inline-flex items-center gap-2 rounded-xl border border-border bg-white px-5 py-2.5 text-sm font-semibold text-foreground shadow-sm transition hover:bg-muted/40"
                            >
                                Batal
                            </Link>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex items-center gap-2 rounded-xl bg-sidebar px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-60"
                            >
                                {form.processing ? <Spinner  /> : <Save size={16} />}
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
