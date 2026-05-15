import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, LogOut, ShieldCheck } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, StudentWithdrawalRequest } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Keluar Pesantren', href: '/santri/withdrawal' },
];

type Props = {
    request: StudentWithdrawalRequest | null;
    openRequest: StudentWithdrawalRequest | null;
    canApply: boolean;
};

const statusLabels: Record<string, string> = {
    awaiting_confirmations: 'Menunggu konfirmasi',
    pending_admin: 'Menunggu persetujuan admin',
    closed_continue: 'Tetap di pesantren',
    approved: 'Disetujui — keluar',
    rejected: 'Ditolak admin',
    cancelled: 'Dibatalkan',
};

const choiceLabel = (c: string | null) =>
    c === 'withdraw' ? 'Keluar pesantren' : c === 'continue' ? 'Tetap di pesantren' : 'Belum diisi';

export default function SantriWithdrawal({ request, openRequest, canApply }: Props) {
    const active = openRequest ?? request;
    const canSubmitSantri =
        canApply &&
        active?.status === 'awaiting_confirmations' &&
        active.santri_choice == null;

    const form = useForm({
        choice: 'continue' as 'withdraw' | 'continue',
        reason: '',
        effective_date: '',
    });

    function submitChoice(e: React.FormEvent) {
        e.preventDefault();
        form.post('/santri/withdrawal/choice', { preserveScroll: true });
    }

    function cancelRequest() {
        if (!confirm('Batalkan permohonan ini?')) return;
        router.post('/santri/withdrawal/cancel', {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Keluar Pesantren" />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Keputusan Keluar Pesantren</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Konfirmasi bersama wali. Keputusan wali meng-override keputusan santri. Admin menyetujui
                        sebelum status keluar berlaku.
                    </p>
                </div>

                <FlashMessage />

                {!canApply ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            Anda tidak dapat mengajukan permohonan baru karena status santri bukan aktif.
                        </CardContent>
                    </Card>
                ) : null}

                {active ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ShieldCheck className="h-4 w-4" />
                                Status permohonan
                            </CardTitle>
                            <CardDescription>
                                <Badge variant="outline">{statusLabels[active.status] ?? active.status}</Badge>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border p-3">
                                    <p className="font-medium text-muted-foreground">Keputusan santri</p>
                                    <p className="mt-1 font-semibold">{choiceLabel(active.santri_choice)}</p>
                                    {active.santri_reason ? (
                                        <p className="mt-2 text-muted-foreground">{active.santri_reason}</p>
                                    ) : null}
                                </div>
                                <div className="rounded-lg border p-3 border-amber-200 bg-amber-50/50">
                                    <p className="font-medium text-muted-foreground">Keputusan wali (berlaku)</p>
                                    <p className="mt-1 font-semibold">{choiceLabel(active.wali_choice)}</p>
                                    {active.wali_reason ? (
                                        <p className="mt-2 text-muted-foreground">{active.wali_reason}</p>
                                    ) : null}
                                </div>
                            </div>
                            {active.resolved_choice ? (
                                <p className="text-muted-foreground">
                                    Keputusan efektif setelah keduanya mengisi:{' '}
                                    <strong>{choiceLabel(active.resolved_choice)}</strong>
                                </p>
                            ) : null}
                            {active.rejection_reason ? (
                                <p className="text-destructive">Alasan penolakan admin: {active.rejection_reason}</p>
                            ) : null}
                            {openRequest ? (
                                <Button type="button" variant="outline" onClick={cancelRequest}>
                                    Batalkan permohonan
                                </Button>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                {canSubmitSantri ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <LogOut className="h-4 w-4" />
                                Konfirmasi Anda
                            </CardTitle>
                            <CardDescription>
                                Setelah Anda mengisi, wali juga harus mengonfirmasi di akun wali.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitChoice} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Pilihan</Label>
                                    <select
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.choice}
                                        onChange={(e) =>
                                            form.setData('choice', e.target.value as 'withdraw' | 'continue')
                                        }
                                    >
                                        <option value="continue">Tetap melanjutkan di pesantren</option>
                                        <option value="withdraw">Keluar / tidak melanjutkan</option>
                                    </select>
                                </div>
                                {form.data.choice === 'withdraw' ? (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="reason">Alasan</Label>
                                            <textarea
                                                id="reason"
                                                className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                value={form.data.reason}
                                                onChange={(e) => form.setData('reason', e.target.value)}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="effective_date">Tanggal efektif (opsional)</Label>
                                            <input
                                                id="effective_date"
                                                type="date"
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                value={form.data.effective_date}
                                                onChange={(e) => form.setData('effective_date', e.target.value)}
                                            />
                                        </div>
                                        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                                            Keluar pesantren bukan izin pulang sementara. Status akan berubah setelah
                                            admin menyetujui.
                                        </div>
                                    </>
                                ) : null}
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Menyimpan...' : 'Simpan keputusan santri'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : canApply && active?.status === 'awaiting_confirmations' && active.santri_choice ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            Keputusan santri sudah tersimpan. Menunggu konfirmasi wali.
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
