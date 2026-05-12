import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, BookOpen, BellRing, CircleHelp, KeyRound, Layers3, Link2, Mail, Phone, Save, Shield, User } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import InputError from '@/components/input-error';
import type { EmProfileFormData } from '@/components/student-profile';
import {
    EmProfileForm,
    ProfileSectionCard,
    emProfileDefaults,
} from '@/components/student-profile';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem, Guardian, Student } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Profil', href: '/santri/profile' },
    { title: 'Edit Profil', href: '/santri/profile/edit' },
];

type Props = {
    student: Student;
    account: {
        name: string;
        email: string;
        whatsapp_phone: string | null;
        google_connected: boolean;
    };
};

const guardianRelationships = [
    'ayah', 'ibu', 'kakak', 'paman', 'bibi',
    'kakek', 'nenek', 'wali', 'lainnya',
];

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

function LockedTile({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1 rounded-xl bg-muted/40 border border-border/70 px-4 py-3 shadow-sm">
            <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{label}</span>
            <span className="text-sm font-medium text-foreground/90">{value}</span>
        </div>
    );
}

function StyledInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <Input
            {...props}
            className="h-11 rounded-xl border-border bg-white text-sm text-foreground shadow-sm transition-all placeholder:text-muted-foreground focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-muted/40 disabled:text-muted-foreground"
        />
    );
}

export default function SantriProfileEdit({ student, account }: Props) {
    const ep = student.emis_profile;

    const form = useForm<{
        full_name: string;
        nik: string;
        birth_place: string;
        birth_date: string;
        gender: 'L' | 'P';
        address: string;
        whatsapp_phone: string;
        google_connected: boolean;
        em_profile: EmProfileFormData;
    }>({
        full_name: student.full_name ?? '',
        nik: student.nik ?? '',
        birth_place: student.birth_place ?? '',
        birth_date: normalizeDateForInput(student.birth_date),
        gender: student.gender ?? 'L',
        address: student.address ?? '',
        whatsapp_phone: account.whatsapp_phone ?? '',
        google_connected: Boolean(account.google_connected),
        em_profile: emProfileDefaults(ep),
    });

    function handleEmProfileChange(field: keyof EmProfileFormData, value: string) {
        form.setData('em_profile', { ...form.data.em_profile, [field]: value });
    }

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        form.put('/santri/profile');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Profil Saya" />

            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">

                    {/* Header */}
                    <div className="mb-8">
                        <Link
                            href="/santri/profile"
                            className="mb-6 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-muted-foreground shadow-sm border border-border transition-all hover:bg-muted/40 hover:text-foreground active:scale-[0.98]"
                        >
                            <ArrowLeft size={16} />
                            Kembali ke Profil
                        </Link>

                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-sidebar text-white shadow-lg">
                                <BookOpen size={24} strokeWidth={1.5} />
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold leading-none text-foreground sm:text-4xl">Edit Profil</h1>
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    Perbarui data diri, data EMIS, dan informasi wali Anda.
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
                                        {student.user?.role?.name ?? 'Santri'}
                                    </span>
                                </div>
                            </div>
                            <Link
                                href="/santri/profile"
                                className="inline-flex items-center gap-2 rounded-xl border border-white/80 bg-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/30"
                            >
                                <ArrowLeft size={16} />
                                Kembali
                            </Link>
                        </div>
                    </div>

                    <form onSubmit={submitProfile} className="space-y-6">
                        {/* Hubungkan Akun */}
                        <ProfileSectionCard icon={Link2} title="Hubungkan Akun" subtitle="Email & Google plus nomor WhatsApp untuk kontak utama">
                            <div className="py-6 space-y-3">
                                <div className="flex items-center gap-3 rounded-xl border border-border bg-white px-4 py-3">
                                    <Mail size={18} className="text-emerald-700" />
                                    <div className="flex-1">
                                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Email</p>
                                        <p className="text-sm font-medium text-foreground">{account.email ?? 'Belum diatur'}</p>
                                    </div>
                                    <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100">Terhubung</Badge>
                                </div>
                                <div className="flex items-center gap-3 rounded-xl border border-border bg-white px-4 py-3">
                                    <BadgeCheck size={18} className="text-emerald-700" />
                                    <div className="flex-1">
                                        <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Google</p>
                                        <p className="text-sm text-muted-foreground">Toggle koneksi akun Google</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => form.setData('google_connected', !form.data.google_connected)}
                                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${form.data.google_connected ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'}`}
                                    >
                                        {form.data.google_connected ? 'Terhubung' : 'Belum'}
                                    </button>
                                </div>
                                <Field label="Nomor WhatsApp" span2>
                                    <div className="relative">
                                        <Phone size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                                        <StyledInput
                                            value={form.data.whatsapp_phone}
                                            onChange={(e) => form.setData('whatsapp_phone', e.target.value)}
                                            placeholder="+62 895 418 331 918"
                                            className="pl-9"
                                        />
                                    </div>
                                </Field>
                            </div>
                        </ProfileSectionCard>

                        {/* Data Santri */}
                        <ProfileSectionCard icon={User} title="Data Santri" subtitle="Pastikan informasi dasar ini sesuai dengan dokumen resmi">
                            <div className="py-6">
                                {/* Locked info */}
                                <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <LockedTile label="NIS" value={<span className="font-mono tracking-wider">{student.nis}</span>} />
                                    <LockedTile label="Tahun Masuk" value={String(student.admission_year)} />
                                    <LockedTile
                                        label="Status"
                                        value={
                                            <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-100 border-none shadow-none font-medium mt-0.5">
                                                {student.status}
                                            </Badge>
                                        }
                                    />
                                    <LockedTile label="Kelas" value={student.current_class?.name ?? 'Belum ada'} />
                                </div>

                                <hr className="mb-8 border-border/70" />

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Field label="Nama Lengkap" required span2>
                                        <StyledInput
                                            value={form.data.full_name}
                                            onChange={(e) => form.setData('full_name', e.target.value)}
                                            placeholder="Sesuai Akta Kelahiran / Ijazah"
                                        />
                                        <InputError message={form.errors.full_name} />
                                    </Field>

                                    <Field label="NIK">
                                        <StyledInput
                                            value={form.data.nik}
                                            onChange={(e) => form.setData('nik', e.target.value)}
                                            maxLength={16}
                                            placeholder="16 digit NIK pada Kartu Keluarga"
                                            className="font-mono tracking-widest placeholder:tracking-normal placeholder:font-sans"
                                        />
                                        <InputError message={form.errors.nik} />
                                    </Field>

                                    <Field label="Jenis Kelamin" required>
                                        <Select value={form.data.gender} onValueChange={(v: 'L' | 'P') => form.setData('gender', v)}>
                                            <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm text-foreground shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                                <SelectValue />
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
                                            placeholder="Kota / Kabupaten kelahiran"
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

                                    <Field label="Alamat Lengkap" span2>
                                        <textarea
                                            rows={3}
                                            className="w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-foreground shadow-sm placeholder:text-muted-foreground transition-all focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                            value={form.data.address}
                                            onChange={(e) => form.setData('address', e.target.value)}
                                            placeholder="Nama Jalan, RT/RW, Dusun, Desa/Kelurahan, Kecamatan..."
                                        />
                                        <InputError message={form.errors.address} />
                                    </Field>
                                </div>

                                <div className="mt-8 flex items-center gap-4 border-t border-border/70 pt-6">
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-sidebar px-6 py-3 text-sm font-semibold text-white shadow-md transition-all hover:bg-sidebar hover:shadow-lg active:scale-[0.98] disabled:opacity-70 disabled:pointer-events-none"
                                    >
                                        {form.processing ? <Spinner className="text-white" /> : <Save size={18} />}
                                        Simpan Semua Perubahan
                                    </button>
                                    <span className="text-xs text-muted-foreground">Semua section tersimpan sekaligus.</span>
                                </div>
                            </div>
                        </ProfileSectionCard>

                        {/* Data EMIS */}
                        <EmProfileForm
                            data={form.data.em_profile}
                            onChange={handleEmProfileChange}
                            showCatatan={false}
                            nismReadOnly
                        />

                        {/* Pengaturan Akun */}
                        <ProfileSectionCard icon={Layers3} title="Pengaturan Akun" subtitle="Ringkasan cepat sesuai tampilan profil mobile">
                            <div className="py-6 space-y-3">
                                {[
                                    { icon: KeyRound, title: 'Ubah Password', sub: 'Terakhir diubah 3 bulan lalu' },
                                    { icon: BellRing, title: 'Perizinan Notifikasi', sub: 'Push & Email Aktif' },
                                    { icon: CircleHelp, title: 'Bantuan & FAQ', sub: 'Panduan penggunaan App' },
                                    { icon: Layers3, title: 'Versi Aplikasi', sub: 'v.1.4.2' },
                                ].map(({ icon: Icon, title, sub }) => (
                                    <div key={title} className="flex items-center gap-3 rounded-xl border border-border bg-white px-4 py-3">
                                        <Icon size={18} className="text-emerald-700" />
                                        <div>
                                            <p className="text-sm font-semibold text-foreground">{title}</p>
                                            <p className="text-xs text-muted-foreground">{sub}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </ProfileSectionCard>
                    </form>

                    {/* Guardian Section */}
                    <div className="mt-6">
                        <ProfileSectionCard
                            icon={Shield}
                            title="Data Wali"
                            subtitle="Kelola informasi orang tua atau wali yang dapat dihubungi"
                        >
                            {student.guardians && student.guardians.length > 0 ? (
                                <div className="py-4 space-y-6">
                                    {student.guardians.map((guardian, idx) => (
                                        <GuardianEditor key={guardian.id} guardian={guardian} index={idx + 1} />
                                    ))}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/40 py-12 px-4 text-center my-4">
                                    <div className="rounded-full bg-muted p-4 mb-4">
                                        <Shield size={24} className="text-muted-foreground" strokeWidth={1.5} />
                                    </div>
                                    <h3 className="text-xl font-semibold text-foreground">Belum ada data wali</h3>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        Hubungi pihak administrasi pesantren untuk menambahkan data wali.
                                    </p>
                                </div>
                            )}
                        </ProfileSectionCard>
                    </div>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}

function GuardianEditor({ guardian, index }: { guardian: Guardian; index: number }) {
    const form = useForm({
        full_name: guardian.full_name ?? '',
        nik: guardian.nik ?? '',
        phone: guardian.phone ?? '',
        email: guardian.email ?? '',
        occupation: guardian.occupation ?? '',
        income_band: guardian.income_band ?? '',
        relationship: guardian.relationship ?? guardian.pivot?.relationship ?? 'wali',
    });

    function submitGuardian(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/santri/profile/guardians/${guardian.id}`, { preserveScroll: true });
    }

    const initials = guardian.full_name
        ? guardian.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
        : '?';

    return (
        <form
            onSubmit={submitGuardian}
            className="rounded-2xl border border-border bg-muted/50 p-6 transition-all hover:border-border hover:shadow-sm"
        >
            <div className="mb-6 flex items-center gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sidebar text-lg font-bold text-white shadow-sm">
                    {initials}
                </div>
                <div>
                    <p className="text-xl font-bold leading-tight text-foreground">
                        {guardian.full_name || `Wali #${index}`}
                    </p>
                    <div className="flex items-center gap-2 mt-0.5">
                        <Badge variant="outline" className="bg-white text-xs font-medium text-muted-foreground border-border px-2 py-0">
                            Wali {index}
                        </Badge>
                        <span className="text-xs font-medium text-emerald-600 capitalize">
                            • {guardian.pivot?.relationship ?? guardian.relationship ?? 'Wali'}
                        </span>
                    </div>
                </div>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                {(
                    [
                        { label: 'Nama Lengkap', field: 'full_name' as const, required: true, span2: true, placeholder: 'Sesuai KTP', mono: false, type: 'text' },
                        { label: 'NIK', field: 'nik' as const, placeholder: '16 digit NIK', mono: true, type: 'text' },
                        { label: 'Nomor Telepon', field: 'phone' as const, placeholder: '08xx-xxxx-xxxx', mono: false, type: 'text' },
                        { label: 'Email', field: 'email' as const, type: 'email', placeholder: 'contoh@email.com', mono: false },
                        { label: 'Pekerjaan', field: 'occupation' as const, placeholder: 'Pekerjaan saat ini', mono: false, type: 'text' },
                        { label: 'Rentang Penghasilan', field: 'income_band' as const, placeholder: 'Rp 3.000.000 – Rp 5.000.000', mono: false, type: 'text' },
                    ] satisfies { label: string; field: keyof typeof form.data; required?: boolean; span2?: boolean; placeholder: string; mono: boolean; type: string }[]
                ).map(({ label, field, required, span2, placeholder, mono, type }) => (
                    <div key={field} className={`flex flex-col gap-1.5 ${span2 ? 'sm:col-span-2' : ''}`}>
                        <label className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            {label}
                            {required && <span className="ml-1 text-rose-500">*</span>}
                        </label>
                        <Input
                            type={type ?? 'text'}
                            value={form.data[field] as string}
                            onChange={(e) => form.setData(field, e.target.value)}
                            placeholder={placeholder}
                            className={`h-11 rounded-xl border-border bg-white text-sm text-foreground shadow-sm transition-all placeholder:text-muted-foreground focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 ${mono ? 'font-mono tracking-widest placeholder:tracking-normal placeholder:font-sans' : ''}`}
                        />
                        <InputError message={form.errors[field]} />
                    </div>
                ))}

                <div className="flex flex-col gap-1.5">
                    <label className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                        Hubungan <span className="text-rose-500">*</span>
                    </label>
                    <Select value={form.data.relationship} onValueChange={(v) => form.setData('relationship', v)}>
                        <SelectTrigger className="h-11 rounded-xl border-border bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {guardianRelationships.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {item.charAt(0).toUpperCase() + item.slice(1)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.relationship} />
                </div>
            </div>

            <div className="mt-6 border-t border-border pt-5 flex justify-end">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex items-center gap-2 rounded-xl border border-border bg-white px-5 py-2.5 text-sm font-semibold text-foreground/90 shadow-sm transition-all hover:border-border hover:bg-muted/40 hover:text-foreground active:scale-[0.98] disabled:opacity-60 disabled:pointer-events-none"
                >
                    {form.processing ? <Spinner className="text-muted-foreground" /> : <Save size={16} />}
                    Simpan Data Wali
                </button>
            </div>
        </form>
    );
}
