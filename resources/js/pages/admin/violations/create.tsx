import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student, ViolationType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pelanggaran', href: '/admin/violations' },
    { title: 'Catat Pelanggaran', href: '/admin/violations/create' },
];

type Props = {
    students: Pick<Student, 'id' | 'nis' | 'full_name'>[];
    violationTypes: ViolationType[];
};

const catLabels: Record<string, string> = { ringan: 'Ringan', sedang: 'Sedang', berat: 'Berat' };

export default function ViolationCreate({ students, violationTypes }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        student_id: '', violation_type_id: '', date: new Date().toISOString().split('T')[0], description: '',
    });

    const selected = violationTypes.find((t) => String(t.id) === data.violation_type_id);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/violations');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catat Pelanggaran" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Catat Pelanggaran" description="Pilih santri dan jenis pelanggaran" />
                <form onSubmit={handleSubmit} className="max-w-lg space-y-6">
                    <div className="grid gap-2">
                        <Label>Santri</Label>
                        <Select value={data.student_id} onValueChange={(v) => setData('student_id', v)}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih santri" /></SelectTrigger>
                            <SelectContent>{students.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.full_name} ({s.nis})</SelectItem>)}</SelectContent>
                        </Select>
                        <InputError message={errors.student_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Jenis Pelanggaran</Label>
                        <Select value={data.violation_type_id} onValueChange={(v) => setData('violation_type_id', v)}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih jenis" /></SelectTrigger>
                            <SelectContent>{violationTypes.map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.name} ({t.points} poin - {catLabels[t.category]})</SelectItem>)}</SelectContent>
                        </Select>
                        <InputError message={errors.violation_type_id} />
                        {selected && <p className="text-sm text-muted-foreground"><Badge variant={selected.category === 'berat' ? 'destructive' : 'secondary'}>{selected.points} poin</Badge></p>}
                    </div>
                    <div className="grid gap-2">
                        <Label>Tanggal</Label>
                        <Input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                        <InputError message={errors.date} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Keterangan</Label>
                        <textarea className="border-input bg-background flex min-h-20 w-full rounded-md border px-3 py-2 text-sm" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>{processing && <Spinner />}Simpan</Button>
                        <Button variant="outline" asChild><Link href="/admin/violations">Batal</Link></Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
