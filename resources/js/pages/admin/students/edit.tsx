import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import Heading from '@/components/heading';
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
    classes: (Pick<SchoolClass, 'id' | 'name' | 'level'>)[];
};

export default function StudentEdit({ student, classes }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
        { title: 'Edit', href: `/admin/students/${student.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
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
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/students/${student.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit - ${student.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Edit Data Santri" description={`Mengubah data ${student.full_name}`} />

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
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
                                            {c.name} ({c.level})
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

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan Perubahan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/admin/students/${student.id}`}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
