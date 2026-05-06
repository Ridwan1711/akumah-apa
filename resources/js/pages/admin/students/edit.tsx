import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, User } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Heading from '@/components/heading';
import { AppSelect, type SelectOption } from '@/components/manhood';
import { EmProfileForm, ProfileSectionCard, emProfileDefaults, type EmProfileFormData } from '@/components/student-profile';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SchoolClass, Student } from '@/types';

type Props = {
    student: Student;
    classes: (Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>)[];
};

export default function StudentEdit({ student, classes }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
        { title: 'Edit', href: `/admin/students/${student.id}/edit` },
    ];

    const ep = student.emis_profile;

    const { data, setData, put, processing, errors } = useForm<{
        user_id: string;
        nis: string;
        nik: string;
        full_name: string;
        birth_place: string;
        birth_date: string;
        gender: 'L' | 'P';
        address: string;
        status: 'active' | 'alumni' | 'keluar' | 'wafat';
        admission_year: string;
        current_class_id: string;
        em_profile: EmProfileFormData;
    }>({
        user_id: student.user_id ? String(student.user_id) : '',
        nis: student.nis,
        nik: student.nik ?? '',
        full_name: student.full_name,
        birth_place: student.birth_place ?? '',
        birth_date: student.birth_date ?? '',
        gender: student.gender,
        address: student.address ?? '',
        status: student.status,
        admission_year: String(student.admission_year),
        current_class_id: student.current_class_id ? String(student.current_class_id) : '',
        em_profile: emProfileDefaults(ep),
    });

    const [existingUserOptions, setExistingUserOptions] = useState<SelectOption[]>(() => {
        if (!student.user) return [];
        return [{ value: String(student.user.id), label: `${student.user.name} (${student.user.email})` }];
    });
    const [isLoadingExistingUsers, setIsLoadingExistingUsers] = useState(false);
    const selectedExistingUserOption = existingUserOptions.find((item) => item.value === data.user_id) ?? null;

    async function loadEligibleUsers(searchTerm = '') {
        setIsLoadingExistingUsers(true);
        try {
            const url = new URL('/admin/students/eligible-users', window.location.origin);
            if (searchTerm.trim() !== '') url.searchParams.set('search', searchTerm.trim());
            if (student.user_id) url.searchParams.set('include_user_id', String(student.user_id));

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            const options: SelectOption[] = (payload?.data ?? []).map((u: { id: number; name: string; email: string }) => ({
                value: String(u.id),
                label: `${u.name} (${u.email})`,
            }));
            setExistingUserOptions(options);
        } catch {
            setExistingUserOptions(
                student.user
                    ? [{ value: String(student.user.id), label: `${student.user.name} (${student.user.email})` }]
                    : [],
            );
        } finally {
            setIsLoadingExistingUsers(false);
        }
    }

    function handleEmProfileChange(field: keyof EmProfileFormData, value: string) {
        setData('em_profile', { ...data.em_profile, [field]: value });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/students/${student.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit - ${student.full_name}`} />
            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                    <Heading title="Edit Data Santri" description={`Mengubah data ${student.full_name}`} />

                    <form onSubmit={handleSubmit} className="mt-6 space-y-6">
                        {/* Data Utama */}
                        <ProfileSectionCard icon={User} title="Data Santri" subtitle="Informasi dasar akademik">
                            <div className="py-6 space-y-5">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nis">NIS *</Label>
                                        <Input id="nis" value={data.nis} onChange={(e) => setData('nis', e.target.value)} required />
                                        <InputError message={errors.nis} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nik">NIK</Label>
                                        <Input id="nik" value={data.nik} onChange={(e) => setData('nik', e.target.value)} maxLength={16} />
                                        <InputError message={errors.nik} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="existing-user">Akun User (Opsional)</Label>
                                    <AppSelect
                                        inputId="existing-user"
                                        placeholder="Cari user..."
                                        options={existingUserOptions}
                                        isLoading={isLoadingExistingUsers}
                                        value={selectedExistingUserOption}
                                        onChange={(option) => setData('user_id', String(option?.value ?? ''))}
                                        onInputChange={(value, meta) => {
                                            if (meta.action === 'input-change') void loadEligibleUsers(value);
                                            return value;
                                        }}
                                        onMenuOpen={() => {
                                            if (existingUserOptions.length === 0) void loadEligibleUsers();
                                        }}
                                    />
                                    <InputError message={errors.user_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="full_name">Nama Lengkap *</Label>
                                    <Input id="full_name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} required />
                                    <InputError message={errors.full_name} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_place">Tempat Lahir</Label>
                                        <Input id="birth_place" value={data.birth_place} onChange={(e) => setData('birth_place', e.target.value)} />
                                        <InputError message={errors.birth_place} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_date">Tanggal Lahir</Label>
                                        <Input id="birth_date" type="date" value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} />
                                        <InputError message={errors.birth_date} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Jenis Kelamin *</Label>
                                        <Select value={data.gender} onValueChange={(v: 'L' | 'P') => setData('gender', v)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="L">Laki-laki</SelectItem>
                                                <SelectItem value="P">Perempuan</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.gender} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="admission_year">Tahun Masuk *</Label>
                                        <Input id="admission_year" type="number" min={2000} max={2099} value={data.admission_year} onChange={(e) => setData('admission_year', e.target.value)} required />
                                        <InputError message={errors.admission_year} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Status *</Label>
                                        <Select value={data.status} onValueChange={(v: 'active' | 'alumni' | 'keluar' | 'wafat') => setData('status', v)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Pilih" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">Aktif</SelectItem>
                                                <SelectItem value="alumni">Alumni</SelectItem>
                                                <SelectItem value="keluar">Keluar</SelectItem>
                                                <SelectItem value="wafat">Wafat</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Kelas Saat Ini</Label>
                                        <Select value={data.current_class_id} onValueChange={(v) => setData('current_class_id', v)}>
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Belum dipilih" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {classes.map((c) => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.current_class_id} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Alamat</Label>
                                    <textarea
                                        id="address"
                                        className="border-input bg-background flex min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                    />
                                    <InputError message={errors.address} />
                                </div>
                            </div>
                        </ProfileSectionCard>

                        {/* Data EMIS */}
                        <EmProfileForm
                            data={data.em_profile}
                            onChange={handleEmProfileChange}
                            showCatatan
                        />

                        <div className="flex items-center gap-3 pt-2">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Simpan Perubahan
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/admin/students/${student.id}`}>Batal</Link>
                            </Button>
                        </div>
                    </form>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}
