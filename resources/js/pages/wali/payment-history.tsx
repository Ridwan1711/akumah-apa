import { Head, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, Payment } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Riwayat Bayar', href: '/wali/payment-history' },
];

const statusLabels: Record<string, string> = { pending: 'Menunggu', verified: 'Terverifikasi', rejected: 'Ditolak' };
const methodLabels: Record<string, string> = { cash: 'Tunai', bank_transfer: 'Transfer', gateway: 'Online' };

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    payments: PaginatedData<Payment>;
};

export default function WaliPaymentHistory({ payments }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Bayar" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Riwayat Pembayaran" description="Semua riwayat pembayaran anak Anda" />

                {payments.data.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <Receipt className="mx-auto mb-2 size-8" />Belum ada riwayat pembayaran.
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">No. Bayar</th>
                                        <th className="px-4 py-3 text-left font-medium">Tagihan</th>
                                        <th className="px-4 py-3 text-left font-medium">Metode</th>
                                        <th className="px-4 py-3 text-right font-medium">Jumlah</th>
                                        <th className="px-4 py-3 text-left font-medium">Tanggal</th>
                                        <th className="px-4 py-3 text-center font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.data.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0 hover:bg-muted/30">
                                            <td className="px-4 py-3"><code className="text-xs">{p.payment_number}</code></td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{p.invoice?.payment_type?.name}</div>
                                                <div className="text-xs text-muted-foreground">{p.invoice?.invoice_number}</div>
                                            </td>
                                            <td className="px-4 py-3">{methodLabels[p.payment_method]}</td>
                                            <td className="px-4 py-3 text-right font-medium">{formatCurrency(p.amount)}</td>
                                            <td className="px-4 py-3">{p.payment_date}</td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant={p.status === 'verified' ? 'default' : p.status === 'rejected' ? 'destructive' : 'outline'}>
                                                    {statusLabels[p.status]}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {payments.last_page > 1 && (
                            <div className="flex justify-center gap-1">
                                {payments.links.map((link, i) => (
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
