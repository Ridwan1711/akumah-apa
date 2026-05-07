import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import type { Auth, BreadcrumbItem, DiniyahClass, Invoice, PaginatedData, PaymentType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Laporan Keuangan', href: '/admin/payment-reports' },
    { title: 'Tunggakan', href: '/admin/payment-reports/arrears' },
];

const statusLabels: Record<string, string> = {
    pending: 'Belum Bayar', partial: 'Sebagian', overdue: 'Jatuh Tempo',
};
const statusColors: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'outline', partial: 'secondary', overdue: 'destructive',
};

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    invoices: PaginatedData<Invoice>;
    classes: Pick<DiniyahClass, 'id' | 'name'>[];
    paymentTypes: Pick<PaymentType, 'id' | 'name'>[];
    filters: { class_id?: string; payment_type_id?: string };
};

export default function ArrearsReport({ invoices, classes, paymentTypes, filters }: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canViewInvoice = can(auth, 'invoice.view');

    function handleFilter(key: string, value: string) {
        router.get('/admin/payment-reports/arrears', { ...filters, [key]: value || undefined, page: undefined }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daftar Tunggakan" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Daftar Tunggakan" description="Tagihan yang belum lunas atau jatuh tempo" />

                <div className="flex gap-3">
                    <Select value={filters.class_id ?? ''} onValueChange={(v) => handleFilter('class_id', v)}>
                        <SelectTrigger className="w-[180px]"><SelectValue placeholder="Semua Kelas" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="_none">Semua Kelas</SelectItem>
                            {classes.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <Select value={filters.payment_type_id ?? ''} onValueChange={(v) => handleFilter('payment_type_id', v)}>
                        <SelectTrigger className="w-[180px]"><SelectValue placeholder="Semua Jenis" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="_none">Semua Jenis</SelectItem>
                            {paymentTypes.map((pt) => <SelectItem key={pt.id} value={String(pt.id)}>{pt.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                {invoices.data.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <AlertTriangle className="mx-auto mb-2 size-8" />Tidak ada tunggakan.
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">Santri</th>
                                        <th className="px-4 py-3 text-left font-medium">Kelas</th>
                                        <th className="px-4 py-3 text-left font-medium">Jenis Bayar</th>
                                        <th className="px-4 py-3 text-right font-medium">Tagihan</th>
                                        <th className="px-4 py-3 text-left font-medium">Jatuh Tempo</th>
                                        <th className="px-4 py-3 text-center font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.data.map((inv) => (
                                        <tr key={inv.id} className="border-b last:border-0 hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{inv.student?.full_name}</div>
                                                <div className="text-xs text-muted-foreground">{inv.student?.nis}</div>
                                            </td>
                                            <td className="px-4 py-3">{inv.student?.current_class?.name}</td>
                                            <td className="px-4 py-3">{inv.payment_type?.name}</td>
                                            <td className="px-4 py-3 text-right font-medium">{formatCurrency(inv.final_amount)}</td>
                                            <td className="px-4 py-3">{inv.due_date}</td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant={statusColors[inv.status]}>{statusLabels[inv.status] ?? inv.status}</Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {canViewInvoice ? (
                                                    <Button size="sm" variant="ghost" asChild>
                                                        <Link href={`/admin/invoices/${inv.id}`}>Detail</Link>
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {invoices.last_page > 1 && (
                            <div className="flex justify-center gap-1">
                                {invoices.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
