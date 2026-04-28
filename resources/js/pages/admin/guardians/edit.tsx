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
import type { BreadcrumbItem, Guardian, Student } from '@/types';

type Props = {
    student: Student;
    guardian: Guardian;
};

const relationships = ['ayah', 'ibu', 'kakak', 'paman', 'bibi', 'kakek', 'nenek', 'wali', 'lainnya'];

export default function GuardianEdit({ student, guardian }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
        { title: `Edit Wali: ${guardian.full_name}`, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        full_name: guardian.full_name,
        nik: guardian.nik ?? '',
        phone: guardian.phone ?? '',
        email: guardian.email ?? '',
        occupation: guardian.occupation ?? '',
        income_band: guardian.income_band ?? '',
        relationship: guardian.relationship ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/students/${student.id}/guardians/${guardian.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Wali - ${guardian.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Edit Wali Santri" description={`Wali dari ${student.full_name} (${student.nis})`} />

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="full_name">Nama Lengkap *</Label>
                        <Input id="full_name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} required />
                        <InputError message={errors.full_name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="nik">NIK</Label>
                            <Input id="nik" value={data.nik} onChange={(e) => setData('nik', e.target.value)} maxLength={16} />
                            <InputError message={errors.nik} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Hubungan *</Label>
                            <Select value={data.relationship} onValueChange={(v) => setData('relationship', v)}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih hubungan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {relationships.map((r) => (
                                        <SelectItem key={r} value={r} className="capitalize">{r}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.relationship} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">No. Telepon</Label>
                            <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            <InputError message={errors.phone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            <InputError message={errors.email} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="occupation">Pekerjaan</Label>
                            <Input id="occupation" value={data.occupation} onChange={(e) => setData('occupation', e.target.value)} />
                            <InputError message={errors.occupation} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="income_band">Penghasilan</Label>
                            <Input id="income_band" value={data.income_band} onChange={(e) => setData('income_band', e.target.value)} placeholder="misal: 3-5 Juta" />
                            <InputError message={errors.income_band} />
                        </div>
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
