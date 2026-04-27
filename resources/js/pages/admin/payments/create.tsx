import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Invoice } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pembayaran', href: '/admin/payments' },
    { title: 'Catat Bayar', href: '#' },
];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    selectedInvoice: Invoice | null;
    unpaidInvoices: Invoice[];
};

export default function PaymentCreate({ selectedInvoice, unpaidInvoices }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        invoice_id: selectedInvoice ? String(selectedInvoice.id) : '',
        amount: 0,
        payment_method: 'cash' as string,
        payment_date: new Date().toISOString().split('T')[0],
        proof_file: null as File | null,
        notes: '',
    });

    const currentInvoice = unpaidInvoices.find((inv) => String(inv.id) === form.data.invoice_id);

    function handleInvoiceChange(id: string) {
        form.setData('invoice_id', id);
        const inv = unpaidInvoices.find((i) => String(i.id) === id);
        if (inv) {
            form.setData('amount', inv.final_amount);
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/payments', {
            forceFormData: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catat Pembayaran" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Catat Pembayaran" description="Catat pembayaran manual dari santri/wali santri" />
                <FlashMessage />

                <form onSubmit={handleSubmit} className="max-w-xl space-y-6 rounded-lg border p-6">
                    <div className="grid gap-2">
                        <Label>Pilih Tagihan</Label>
                        <Select value={form.data.invoice_id} onValueChange={handleInvoiceChange}>
                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih tagihan" /></SelectTrigger>
                            <SelectContent>
                                {unpaidInvoices.map((inv) => (
                                    <SelectItem key={inv.id} value={String(inv.id)}>
                                        {inv.invoice_number} — {inv.student?.full_name} — {inv.payment_type?.name} — {formatCurrency(inv.final_amount)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.invoice_id} />
                        {currentInvoice && (
                            <p className="text-xs text-muted-foreground">
                                Total tagihan: {formatCurrency(currentInvoice.final_amount)}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Jumlah Bayar (Rp)</Label>
                            <Input type="number" value={form.data.amount} onChange={(e) => form.setData('amount', Number(e.target.value))} min={1} />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Metode</Label>
                            <Select value={form.data.payment_method} onValueChange={(v) => form.setData('payment_method', v)}>
                                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">Tunai</SelectItem>
                                    <SelectItem value="bank_transfer">Transfer Bank</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Tanggal Bayar</Label>
                        <Input type="date" value={form.data.payment_date} onChange={(e) => form.setData('payment_date', e.target.value)} className="w-full sm:w-[200px]" />
                        <InputError message={form.errors.payment_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Bukti Bayar (opsional)</Label>
                        <Input
                            ref={fileRef}
                            type="file"
                            accept="image/*,.pdf"
                            onChange={(e) => form.setData('proof_file', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={form.errors.proof_file} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Catatan</Label>
                        <Input value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Opsional" />
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan Pembayaran
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
