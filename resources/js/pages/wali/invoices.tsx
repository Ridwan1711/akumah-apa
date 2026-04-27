import { Head, Link, router } from '@inertiajs/react';
import { Banknote, Eye } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Invoice, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/wali/invoices' },
];

const statusLabels: Record<string, string> = {
    pending: 'Belum Bayar', paid: 'Lunas', partial: 'Sebagian', overdue: 'Jatuh Tempo', cancelled: 'Dibatalkan',
};
const statusColors: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'outline', paid: 'default', partial: 'secondary', overdue: 'destructive', cancelled: 'secondary',
};
const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type Props = {
    invoices: PaginatedData<Invoice>;
    filters: { status?: string };
};

export default function WaliInvoices({ invoices, filters }: Props) {
    function handleFilter(status: string) {
        router.get('/wali/invoices', { status: status || undefined }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tagihan" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Tagihan Anak" description="Daftar tagihan pembayaran pesantren" />
                <FlashMessage />

                <div className="flex flex-wrap gap-2">
                    {['', 'pending', 'partial', 'overdue', 'paid'].map((s) => (
                        <Button
                            key={s}
                            variant={filters.status === s || (!filters.status && !s) ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleFilter(s)}
                        >
                            {!s ? 'Semua' : statusLabels[s]}
                        </Button>
                    ))}
                </div>

                {invoices.data.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <Banknote className="mx-auto mb-2 size-8" />Tidak ada tagihan.
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {invoices.data.map((inv) => (
                            <div key={inv.id} className="flex flex-col justify-between rounded-lg border p-4">
                                <div className="space-y-2">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="font-semibold">{inv.payment_type?.name}</p>
                                            <p className="text-xs text-muted-foreground">{inv.invoice_number}</p>
                                        </div>
                                        <Badge variant={statusColors[inv.status]}>{statusLabels[inv.status]}</Badge>
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {inv.student?.full_name} &middot; {inv.month ? monthNames[inv.month] : ''} {inv.academic_year?.name}
                                    </div>
                                    <p className="text-lg font-bold">{formatCurrency(inv.final_amount)}</p>
                                    <p className="text-xs text-muted-foreground">Jatuh tempo: {inv.due_date}</p>
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <Button size="sm" variant="outline" asChild className="flex-1">
                                        <Link href={`/wali/invoices/${inv.id}`}><Eye className="mr-1 size-4" />Detail</Link>
                                    </Button>
                                    {inv.status !== 'paid' && inv.status !== 'cancelled' && (
                                        <Button size="sm" asChild className="flex-1">
                                            <Link href={`/wali/invoices/${inv.id}`}>Bayar</Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

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
            </div>
        </AppLayout>
    );
}
