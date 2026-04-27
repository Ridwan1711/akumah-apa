import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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

const relationships = ['ayah', 'ibu', 'kakak', 'paman', 'bibi', 'kakek', 'nenek', 'wali', 'lainnya'];

type Props = {
    student: Student;
    existingGuardians: (Pick<Guardian, 'id' | 'full_name' | 'phone' | 'email'> & { students_count: number })[];
};

export default function GuardianAttach({ student, existingGuardians }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
        { title: 'Tambahkan Wali yang Ada', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        guardian_id: '',
        relationship: 'ayah',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(`/admin/students/${student.id}/guardians/attach`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambahkan Wali yang Ada" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Tambahkan Wali yang Sudah Ada"
                    description={`Pilih wali yang sudah terdaftar untuk ditambahkan ke ${student.full_name} (${student.nis})`}
                />

                {existingGuardians.length === 0 ? (
                    <div className="rounded-lg border p-6 text-center text-muted-foreground">
                        <UserPlus className="mx-auto mb-2 size-8" />
                        <p>Tidak ada wali lain yang bisa ditambahkan. Semua wali sudah terdaftar untuk santri ini, atau belum ada wali di sistem.</p>
                        <Button variant="outline" className="mt-4" asChild>
                            <Link href={`/admin/students/${student.id}/guardians/create`}>Tambah Wali Baru</Link>
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="max-w-xl space-y-6">
                        <div className="grid gap-2">
                            <Label>Wali *</Label>
                            <Select value={data.guardian_id} onValueChange={(v) => setData('guardian_id', v)} required>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih wali" />
                                </SelectTrigger>
                                <SelectContent>
                                    {existingGuardians.map((g) => (
                                        <SelectItem key={g.id} value={String(g.id)}>
                                            {g.full_name}
                                            {g.phone && ` (${g.phone})`}
                                            {g.students_count > 0 && ` - ${g.students_count} anak`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.guardian_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Hubungan dengan santri ini *</Label>
                            <Select value={data.relationship} onValueChange={(v) => setData('relationship', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {relationships.map((r) => (
                                        <SelectItem key={r} value={r} className="capitalize">
                                            {r}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.relationship} />
                        </div>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={processing || !data.guardian_id}>
                                {processing && <Spinner />}
                                Tambahkan
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/admin/students/${student.id}`}>Batal</Link>
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={`/admin/students/${student.id}/guardians/create`}>Tambah Wali Baru</Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
