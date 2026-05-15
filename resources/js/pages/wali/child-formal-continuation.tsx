import { Head, router, useForm } from '@inertiajs/react';
import { GraduationCap, ShieldCheck } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student, StudentFormalContinuationRequest } from '@/types';

const statusLabels: Record<string, string> = {
    awaiting_confirmations: 'Menunggu konfirmasi',
    pending_admin: 'Menunggu persetujuan admin',
    approved: 'Disetujui',
    rejected: 'Ditolak admin',
    cancelled: 'Dibatalkan',
};

const choiceLabel = (c: string | null) => {
    if (c === 'ma_10') return 'Lanjut MA 10';
    if (c === 'kuliah') return 'Tingkat Kuliah (tetap santri)';
    return 'Belum diisi';
};

type Props = {
    student: Pick<Student, 'id' | 'full_name' | 'nis' | 'status'>;
    request: StudentFormalContinuationRequest | null;
    openRequest: StudentFormalContinuationRequest | null;
    allowedChoices: string[];
};

export default function WaliChildFormalContinuation({ student, request, openRequest, allowedChoices }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Data Anak', href: '/wali/children' },
        { title: student.full_name, href: `/wali/children/${student.id}` },
        { title: 'Lanjut Formal', href: `/wali/children/${student.id}/formal-continuation` },
    ];

    const active = openRequest ?? request;
    const canSubmitWali =
        active?.status === 'awaiting_confirmations' && active.wali_choice == null && allowedChoices.length > 0;

    const form = useForm({
        choice: (allowedChoices[0] ?? 'kuliah') as 'ma_10' | 'kuliah',
    });

    function submitChoice(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/wali/children/${student.id}/formal-continuation/choice`, { preserveScroll: true });
    }

    function cancelRequest() {
        if (!confirm('Batalkan permohonan ini?')) return;
        router.post(`/wali/children/${student.id}/formal-continuation/cancel`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Lanjut Formal — ${student.full_name}`} />
            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Konfirmasi Lanjut Formal</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        {student.full_name} ({student.nis}) — keputusan wali meng-override keputusan santri.
                    </p>
                </div>

                <FlashMessage />

                {!active ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            Belum ada undangan konfirmasi untuk anak Anda.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4" />
                                    Status
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
                                        Batalkan
                                    </Button>
                                ) : null}
                            </CardContent>
                        </Card>

                        {canSubmitWali ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <GraduationCap className="h-4 w-4" />
                                        Pilihan wali
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submitChoice} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Pilihan</Label>
                                            <select
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                value={form.data.choice}
                                                onChange={(e) =>
                                                    form.setData('choice', e.target.value as 'ma_10' | 'kuliah')
                                                }
                                            >
                                                {allowedChoices.includes('ma_10') ? (
                                                    <option value="ma_10">Lanjut MA 10</option>
                                                ) : null}
                                                {allowedChoices.includes('kuliah') ? (
                                                    <option value="kuliah">
                                                        Tidak lanjut MA — tingkat Kuliah (tetap santri)
                                                    </option>
                                                ) : null}
                                            </select>
                                        </div>
                                        <Button type="submit" disabled={form.processing}>
                                            {form.processing ? 'Menyimpan...' : 'Simpan pilihan wali'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        ) : null}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
