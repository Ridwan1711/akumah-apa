import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, LogOut, ShieldCheck } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student, StudentWithdrawalRequest } from '@/types';

type Props = {
    student: Pick<Student, 'id' | 'full_name' | 'nis' | 'status'>;
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

export default function WaliChildWithdrawal({ student, request, openRequest, canApply }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Anak', href: '/wali/children' },
        { title: student.full_name, href: `/wali/children/${student.id}` },
        { title: 'Keluar Pesantren', href: `/wali/children/${student.id}/withdrawal` },
    ];

    const active = openRequest ?? request;
    const canSubmitWali =
        canApply && active?.status === 'awaiting_confirmations' && active.wali_choice == null;

    const form = useForm({
        choice: 'continue' as 'withdraw' | 'continue',
        reason: '',
        effective_date: '',
    });

    function submitChoice(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/wali/children/${student.id}/withdrawal/choice`, { preserveScroll: true });
    }

    function cancelRequest() {
        if (!confirm('Batalkan permohonan ini?')) return;
        router.post(`/wali/children/${student.id}/withdrawal/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Keluar Pesantren — ${student.full_name}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Keputusan Keluar Pesantren</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {student.full_name} ({student.nis}) — keputusan wali meng-override keputusan santri.
                        </p>
                    </div>
                    <Link href={`/wali/children/${student.id}`}>
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-1" />
                            Kembali
                        </Button>
                    </Link>
                </div>

                <FlashMessage />

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
                                </div>
                                <div className="rounded-lg border p-3 border-amber-200 bg-amber-50/50">
                                    <p className="font-medium text-muted-foreground">Keputusan wali (berlaku)</p>
                                    <p className="mt-1 font-semibold">{choiceLabel(active.wali_choice)}</p>
                                </div>
                            </div>
                            {openRequest ? (
                                <Button type="button" variant="outline" onClick={cancelRequest}>
                                    Batalkan permohonan
                                </Button>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                {canSubmitWali ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <LogOut className="h-4 w-4" />
                                Konfirmasi wali
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitChoice} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Pilihan wali (meng-override santri)</Label>
                                    <select
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={form.data.choice}
                                        onChange={(e) =>
                                            form.setData('choice', e.target.value as 'withdraw' | 'continue')
                                        }
                                    >
                                        <option value="continue">Anak tetap di pesantren</option>
                                        <option value="withdraw">Anak keluar / tidak melanjutkan</option>
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
                                        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                                            Admin harus menyetujui sebelum status santri berubah menjadi keluar.
                                        </div>
                                    </>
                                ) : null}
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? 'Menyimpan...' : 'Simpan keputusan wali'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
