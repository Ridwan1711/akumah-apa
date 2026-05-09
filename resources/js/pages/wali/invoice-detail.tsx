import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, Upload } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useRef, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Invoice } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tagihan', href: '/wali/invoices' },
    { title: 'Detail', href: '#' },
];

const statusLabels: Record<string, string> = {
    pending: 'Belum Bayar', paid: 'Lunas', partial: 'Sebagian', overdue: 'Jatuh Tempo', cancelled: 'Dibatalkan',
};
const statusColors: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'outline', paid: 'default', partial: 'secondary', overdue: 'destructive', cancelled: 'secondary',
};
const paymentStatusLabels: Record<string, string> = { pending: 'Menunggu', verified: 'Terverifikasi', rejected: 'Ditolak' };
const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type ChargeResult = {
    payment_id: number;
    payment_method: string;
    va_number?: string;
    bank?: string;
    qr_string?: string;
    qr_url?: string;
    expiry_time?: string;
    amount: number;
    order_id: string;
};

type Props = {
    invoice: Invoice;
    midtransClientKey: string;
};

export default function WaliInvoiceDetail({ invoice, midtransClientKey }: Props) {
    const [showUpload, setShowUpload] = useState(false);
    const [chargeLoading, setChargeLoading] = useState<string | null>(null);
    const [chargeResult, setChargeResult] = useState<ChargeResult | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const uploadForm = useForm({
        amount: invoice.remaining ?? invoice.final_amount,
        proof_file: null as File | null,
        notes: '',
    });

    const canPay = !['paid', 'cancelled'].includes(invoice.status);
    const remaining = invoice.remaining ?? invoice.final_amount;

    function handleUploadProof(e: React.FormEvent) {
        e.preventDefault();
        uploadForm.post(`/wali/invoices/${invoice.id}/upload-proof`, {
            forceFormData: true,
            onSuccess: () => { setShowUpload(false); uploadForm.reset(); },
        });
    }

    function handleCreateCharge(paymentMethod: 'bri_va' | 'qris') {
        setChargeLoading(paymentMethod);
        setChargeResult(null);

        fetch('/wali/payment/create-charge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ invoice_id: invoice.id, payment_method: paymentMethod }),
        })
            .then((res) => res.json())
            .then((data) => {
                setChargeLoading(null);
                if (data.error) {
                    alert(data.error);
                    return;
                }
                setChargeResult(data);
            })
            .catch(() => {
                setChargeLoading(null);
                alert('Terjadi kesalahan saat membuat transaksi.');
            });
    }

    function copyToClipboard(text: string) {
        navigator.clipboard.writeText(text).then(() => alert('Nomor VA berhasil disalin.'));
    }

    function closeChargeDialog() {
        setChargeResult(null);
        router.reload();
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tagihan ${invoice.invoice_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={`Tagihan ${invoice.invoice_number}`} description={invoice.student?.full_name ?? ''} />
                    <Button variant="outline" onClick={() => router.visit('/wali/invoices')}>
                        <ArrowLeft className="mr-1 size-4" /> Kembali
                    </Button>
                </div>
                <FlashMessage />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-4 rounded-lg border p-6">
                        <h3 className="text-lg font-semibold">Detail Tagihan</h3>
                        <div className="grid gap-3 text-sm">
                            <Row label="Status"><Badge variant={statusColors[invoice.status]}>{statusLabels[invoice.status]}</Badge></Row>
                            <Row label="Jenis">{invoice.payment_type?.name}</Row>
                            <Row label="Tahun Ajaran">{invoice.academic_year?.name}</Row>
                            {invoice.month && <Row label="Bulan">{monthNames[invoice.month]}</Row>}
                            <Row label="Jatuh Tempo">{invoice.due_date}</Row>
                            <hr />
                            <Row label="Total Tagihan"><span className="text-lg font-bold">{formatCurrency(invoice.final_amount)}</span></Row>
                            {invoice.discount_amount > 0 && <Row label="Diskon">- {formatCurrency(invoice.discount_amount)}</Row>}
                            <Row label="Terbayar"><span className="text-green-600">{formatCurrency(invoice.total_paid ?? 0)}</span></Row>
                            <Row label="Sisa"><span className="font-bold">{formatCurrency(remaining)}</span></Row>
                            {(invoice.breakdown_items ?? invoice.breakdown ?? []).length > 0 ? (
                                <>
                                    <hr />
                                    <div className="space-y-2">
                                        <div className="text-muted-foreground">Rincian Tagihan</div>
                                        {(invoice.breakdown_items ?? invoice.breakdown ?? []).map((item, index) => (
                                            <div key={`invoice-breakdown-${index}`} className="flex justify-between text-sm">
                                                <span>{item.label}</span>
                                                <span className="font-medium">{formatCurrency(Number(item.amount))}</span>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : null}
                        </div>
                    </div>

                    {canPay && remaining > 0 && midtransClientKey && (
                        <div className="space-y-4 rounded-lg border p-6">
                            <h3 className="text-lg font-semibold">Bayar Online</h3>
                            <div className="flex flex-col gap-3">
                                <Button
                                    onClick={() => handleCreateCharge('bri_va')}
                                    disabled={!!chargeLoading}
                                    variant="outline"
                                    className="w-full justify-start"
                                >
                                    {chargeLoading === 'bri_va' && <Spinner className="mr-2" />}
                                    BRI Virtual Account
                                </Button>
                                <Button
                                    onClick={() => handleCreateCharge('qris')}
                                    disabled={!!chargeLoading}
                                    variant="outline"
                                    className="w-full justify-start"
                                >
                                    {chargeLoading === 'qris' && <Spinner className="mr-2" />}
                                    QRIS (GoPay / ShopeePay)
                                </Button>
                                <Button variant="outline" onClick={() => setShowUpload(!showUpload)} className="w-full">
                                    <Upload className="mr-2 size-4" /> Upload Bukti Transfer
                                </Button>
                            </div>

                            {showUpload && (
                                <form onSubmit={handleUploadProof} className="space-y-4 rounded-lg border p-4">
                                    <div className="grid gap-2">
                                        <Label>Jumlah yang Dibayar (Rp)</Label>
                                        <Input type="number" value={uploadForm.data.amount} onChange={(e) => uploadForm.setData('amount', Number(e.target.value))} min={1} />
                                        <InputError message={uploadForm.errors.amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Bukti Bayar</Label>
                                        <Input ref={fileRef} type="file" accept="image/*,.pdf" onChange={(e) => uploadForm.setData('proof_file', e.target.files?.[0] ?? null)} />
                                        <InputError message={uploadForm.errors.proof_file} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Catatan</Label>
                                        <Input value={uploadForm.data.notes} onChange={(e) => uploadForm.setData('notes', e.target.value)} placeholder="Opsional" />
                                    </div>
                                    <Button type="submit" disabled={uploadForm.processing}>
                                        {uploadForm.processing && <Spinner />} Upload
                                    </Button>
                                </form>
                            )}
                        </div>
                    )}
                </div>

                <div className="space-y-4 rounded-lg border p-6">
                    <h3 className="text-lg font-semibold">Riwayat Pembayaran</h3>
                    {(!invoice.payments || invoice.payments.length === 0) ? (
                        <p className="text-sm text-muted-foreground">Belum ada pembayaran.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-medium">No.</th>
                                        <th className="px-4 py-2 text-left font-medium">Tanggal</th>
                                        <th className="px-4 py-2 text-right font-medium">Jumlah</th>
                                        <th className="px-4 py-2 text-center font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoice.payments.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0">
                                            <td className="px-4 py-2"><code className="text-xs">{p.payment_number}</code></td>
                                            <td className="px-4 py-2">{p.payment_date}</td>
                                            <td className="px-4 py-2 text-right font-medium">{formatCurrency(p.amount)}</td>
                                            <td className="px-4 py-2 text-center">
                                                <Badge variant={p.status === 'verified' ? 'default' : p.status === 'rejected' ? 'destructive' : 'outline'}>
                                                    {paymentStatusLabels[p.status]}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Dialog open={!!chargeResult} onOpenChange={(open) => !open && closeChargeDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {chargeResult?.payment_method === 'bri_va' ? 'BRI Virtual Account' : 'QRIS'}
                        </DialogTitle>
                        <DialogDescription>
                            Selesaikan pembayaran sebelum batas waktu.
                        </DialogDescription>
                    </DialogHeader>
                    {chargeResult && (
                        <div className="space-y-4">
                            {chargeResult.payment_method === 'bri_va' && chargeResult.va_number && (
                                <>
                                    <div className="rounded-lg border bg-muted/50 p-4">
                                        <p className="text-sm text-muted-foreground">Nomor Virtual Account</p>
                                        <p className="text-xl font-mono font-bold">{chargeResult.va_number}</p>
                                        <p className="mt-1 text-sm">Bank BRI</p>
                                    </div>
                                    <p className="text-sm font-medium">Jumlah: {formatCurrency(chargeResult.amount)}</p>
                                    <Button onClick={() => copyToClipboard(chargeResult.va_number!)} className="w-full">
                                        <Copy className="mr-2 size-4" /> Salin Nomor VA
                                    </Button>
                                </>
                            )}
                            {chargeResult.payment_method === 'qris' && (
                                <>
                                    <div className="flex justify-center">
                                        {chargeResult.qr_string ? (
                                            <QRCodeSVG value={chargeResult.qr_string} size={200} level="M" />
                                        ) : chargeResult.qr_url ? (
                                            <img src={chargeResult.qr_url} alt="QR Code" className="size-[200px]" />
                                        ) : null}
                                    </div>
                                    <p className="text-center text-sm font-medium">Jumlah: {formatCurrency(chargeResult.amount)}</p>
                                    <p className="text-center text-xs text-muted-foreground">Scan QR dengan GoPay atau ShopeePay</p>
                                </>
                            )}
                            {chargeResult.expiry_time && (
                                <p className="text-xs text-muted-foreground">Batas waktu: {chargeResult.expiry_time}</p>
                            )}
                            <Button variant="outline" onClick={closeChargeDialog} className="w-full">
                                Tutup
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span>{children}</span>
        </div>
    );
}
