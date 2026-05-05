import { Head, Link, useForm } from '@inertiajs/react';
import { Search, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
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

    const [guardianSearch, setGuardianSearch] = useState('');

    const filteredGuardians = useMemo(() => {
        const q = guardianSearch.toLowerCase().trim();
        if (!q) return existingGuardians;
        return existingGuardians.filter(
            (g) =>
                g.full_name.toLowerCase().includes(q) ||
                (g.phone ?? '').includes(q) ||
                (g.email ?? '').toLowerCase().includes(q),
        );
    }, [existingGuardians, guardianSearch]);

    const selectedGuardian = existingGuardians.find((g) => String(g.id) === data.guardian_id);

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

                            {/* Search box */}
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama, telepon, email..."
                                    value={guardianSearch}
                                    onChange={(e) => setGuardianSearch(e.target.value)}
                                    className="pl-8 pr-8"
                                />
                                {guardianSearch && (
                                    <button
                                        type="button"
                                        onClick={() => setGuardianSearch('')}
                                        className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>

                            {/* Guardian list */}
                            <div className="max-h-64 overflow-y-auto rounded-md border divide-y">
                                {filteredGuardians.length === 0 ? (
                                    <p className="p-4 text-sm text-center text-muted-foreground">
                                        Tidak ada wali yang cocok dengan pencarian.
                                    </p>
                                ) : (
                                    filteredGuardians.map((g) => {
                                        const isSelected = String(g.id) === data.guardian_id;
                                        return (
                                            <button
                                                key={g.id}
                                                type="button"
                                                onClick={() => setData('guardian_id', isSelected ? '' : String(g.id))}
                                                className={`w-full text-left px-4 py-3 hover:bg-muted/50 transition-colors ${isSelected ? 'bg-primary/10 border-l-2 border-primary' : ''}`}
                                            >
                                                <div className="font-medium text-sm">{g.full_name}</div>
                                                <div className="text-xs text-muted-foreground mt-0.5 flex gap-3">
                                                    {g.phone && <span>{g.phone}</span>}
                                                    {g.email && <span>{g.email}</span>}
                                                    {g.students_count > 0 && <span>{g.students_count} anak terdaftar</span>}
                                                </div>
                                            </button>
                                        );
                                    })
                                )}
                            </div>

                            {selectedGuardian && (
                                <p className="text-sm text-primary font-medium">
                                    Dipilih: {selectedGuardian.full_name}
                                </p>
                            )}

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
