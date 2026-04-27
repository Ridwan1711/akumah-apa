import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { AcademicYear, BreadcrumbItem, PaginatedData, PaymentType, Student, StudentDiscount } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Diskon Santri', href: '/admin/student-discounts' },
];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    discounts: PaginatedData<StudentDiscount>;
    students: Pick<Student, 'id' | 'nis' | 'full_name'>[];
    paymentTypes: Pick<PaymentType, 'id' | 'name' | 'code'>[];
    academicYears: Pick<AcademicYear, 'id' | 'name'>[];
    filters: { academic_year_id?: string };
};

export default function StudentDiscountIndex({ discounts, students, paymentTypes, academicYears, filters }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    const form = useForm({
        student_id: '',
        payment_type_id: '',
        academic_year_id: '',
        discount_type: 'fixed' as string,
        discount_value: 0,
        reason: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/student-discounts', {
            onSuccess: () => { setDialogOpen(false); form.reset(); },
        });
    }

    function handleDelete(id: number) {
        if (confirm('Hapus diskon ini?')) router.delete(`/admin/student-discounts/${id}`);
    }

    function handleFilter(key: string, value: string) {
        router.get('/admin/student-discounts', { ...filters, [key]: value || undefined }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Diskon Santri" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Diskon Santri" description="Kelola diskon/keringanan pembayaran per santri" />
                    <Button onClick={() => { form.clearErrors(); setDialogOpen(true); }}><Plus className="mr-2 size-4" />Tambah Diskon</Button>
                </div>
                <FlashMessage />

                <div className="flex gap-3">
                    <Select value={filters.academic_year_id ?? ''} onValueChange={(v) => handleFilter('academic_year_id', v)}>
                        <SelectTrigger className="w-[200px]"><SelectValue placeholder="Semua Tahun Ajaran" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="_none">Semua Tahun Ajaran</SelectItem>
                            {academicYears.map((ay) => <SelectItem key={ay.id} value={String(ay.id)}>{ay.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                {discounts.data.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <Wallet className="mx-auto mb-2 size-8" />Belum ada diskon santri.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Santri</th>
                                    <th className="px-4 py-3 text-left font-medium">Jenis Bayar</th>
                                    <th className="px-4 py-3 text-left font-medium">Tahun Ajaran</th>
                                    <th className="px-4 py-3 text-left font-medium">Diskon</th>
                                    <th className="px-4 py-3 text-left font-medium">Alasan</th>
                                    <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {discounts.data.map((d) => (
                                    <tr key={d.id} className="border-b last:border-0 hover:bg-muted/30">
                                        <td className="px-4 py-3 font-medium">{d.student?.full_name} <span className="text-xs text-muted-foreground">({d.student?.nis})</span></td>
                                        <td className="px-4 py-3">{d.payment_type?.name}</td>
                                        <td className="px-4 py-3">{d.academic_year?.name}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {d.discount_type === 'percentage' ? `${d.discount_value}%` : formatCurrency(d.discount_value)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{d.reason || '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button size="sm" variant="ghost" onClick={() => handleDelete(d.id)}>
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent>
                        <form onSubmit={handleSubmit}>
                            <DialogHeader><DialogTitle>Tambah Diskon Santri</DialogTitle></DialogHeader>
                            <div className="grid gap-4 py-4">
                                <div className="grid gap-2">
                                    <Label>Santri</Label>
                                    <Select value={form.data.student_id} onValueChange={(v) => form.setData('student_id', v)}>
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Pilih santri" /></SelectTrigger>
                                        <SelectContent>
                                            {students.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.full_name} ({s.nis})</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.student_id} />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Jenis Pembayaran</Label>
                                        <Select value={form.data.payment_type_id} onValueChange={(v) => form.setData('payment_type_id', v)}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                            <SelectContent>
                                                {paymentTypes.map((pt) => <SelectItem key={pt.id} value={String(pt.id)}>{pt.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.payment_type_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Tahun Ajaran</Label>
                                        <Select value={form.data.academic_year_id} onValueChange={(v) => form.setData('academic_year_id', v)}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                            <SelectContent>
                                                {academicYears.map((ay) => <SelectItem key={ay.id} value={String(ay.id)}>{ay.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.academic_year_id} />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Tipe Diskon</Label>
                                        <Select value={form.data.discount_type} onValueChange={(v) => form.setData('discount_type', v)}>
                                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="fixed">Nominal Tetap (Rp)</SelectItem>
                                                <SelectItem value="percentage">Persentase (%)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Nilai Diskon</Label>
                                        <Input type="number" value={form.data.discount_value} onChange={(e) => form.setData('discount_value', Number(e.target.value))} min={0} />
                                        <InputError message={form.errors.discount_value} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Alasan</Label>
                                    <Input value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="Opsional" />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing && <Spinner />}Simpan
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
