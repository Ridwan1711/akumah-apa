import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Eye, XCircle } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kenaikan Kelas', href: '/admin/class-promotion' },
];

type RecapStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

type RecapSummary = {
    id: number;
    status: RecapStatus;
    source_class?: { id: number; name: string };
    period?: {
        id: number;
        academic_year?: { id: number; name: string };
        semester?: { id: number; name: string };
    };
    submitter?: { id: number; name: string } | null;
    reviewer?: { id: number; name: string } | null;
    items_count: number;
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_notes: string | null;
};

type RecapItem = {
    id: number;
    student?: { id: number; nis: string; full_name: string; gender: 'L' | 'P' };
    target_class?: { id: number; name: string } | null;
    applied_class?: { id: number; name: string } | null;
    academic_average: string | number | null;
    personality_score: string | number;
    kitab_reading_score: string | number | null;
    weighted_total: string | number;
    system_recommendation: 'promote' | 'stay';
    final_decision: 'promote' | 'stay' | 'graduate';
    placement_status: 'pending' | 'applied' | 'blocked';
    placement_message: string | null;
    notes: string | null;
};

type SelectedRecap = RecapSummary & {
    items: RecapItem[];
};

type Props = {
    recaps: PaginatedData<RecapSummary>;
    selectedRecap: SelectedRecap | null;
    filters: { status?: string };
    statusOptions: string[];
};

function statusLabel(status: string) {
    if (status === 'all') return 'Semua';
    if (status === 'submitted') return 'Menunggu Approval';
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    if (status === 'draft') return 'Draft';

    return status;
}

function decisionLabel(value: string) {
    if (value === 'promote') return 'Naik';
    if (value === 'graduate') return 'Lulus';

    return 'Tidak Naik';
}

function numberValue(value: string | number | null) {
    if (value === null || value === undefined) return '-';

    return Number(value).toFixed(2);
}

export default function ClassPromotionIndex({ recaps, selectedRecap, filters, statusOptions }: Props) {
    const rejectForm = useForm({ rejection_notes: '' });

    function setStatus(value: string) {
        router.get('/admin/class-promotion', { status: value === 'all' ? 'all' : value }, { preserveState: true, preserveScroll: true });
    }

    function approveRecap(recapId: number) {
        router.post(`/admin/class-promotion/${recapId}/approve`, undefined, { preserveScroll: true });
    }

    function rejectRecap(recapId: number) {
        rejectForm.post(`/admin/class-promotion/${recapId}/reject`, {
            preserveScroll: true,
            onSuccess: () => rejectForm.reset(),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kenaikan Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Approval Kenaikan Kelas"
                    description="Tinjau rekap kenaikan kelas yang diajukan wali kelas sebelum sistem menempatkan santri ke tahun ajaran berikutnya."
                />
                <FlashMessage />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-card p-4">
                    <div className="grid gap-1">
                        <Label className="text-xs">Status</Label>
                        <Select value={filters.status ?? 'submitted'} onValueChange={setStatus}>
                            <SelectTrigger className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabel(status)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-3 py-3 text-left">Kelas</th>
                                    <th className="px-3 py-3 text-left">Periode</th>
                                    <th className="px-3 py-3 text-left">Pengaju</th>
                                    <th className="px-3 py-3 text-center">Santri</th>
                                    <th className="px-3 py-3 text-center">Status</th>
                                    <th className="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recaps.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                                            Belum ada rekap kenaikan kelas untuk filter ini.
                                        </td>
                                    </tr>
                                ) : (
                                    recaps.data.map((recap) => (
                                        <tr key={recap.id} className="border-b last:border-0">
                                            <td className="px-3 py-2 font-medium">{recap.source_class?.name}</td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {recap.period?.academic_year?.name} - {recap.period?.semester?.name}
                                            </td>
                                            <td className="px-3 py-2">{recap.submitter?.name ?? '-'}</td>
                                            <td className="px-3 py-2 text-center">{recap.items_count}</td>
                                            <td className="px-3 py-2 text-center">
                                                <Badge variant={recap.status === 'approved' ? 'default' : recap.status === 'rejected' ? 'destructive' : 'secondary'}>
                                                    {statusLabel(recap.status)}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/admin/class-promotion?status=${filters.status ?? 'submitted'}&recap_id=${recap.id}`}>
                                                        <Eye className="mr-1 size-3" />
                                                        Detail
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        {!selectedRecap ? (
                            <div className="py-10 text-center text-sm text-muted-foreground">Pilih rekap untuk melihat detail.</div>
                        ) : (
                            <div className="grid gap-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="text-lg font-semibold">{selectedRecap.source_class?.name}</div>
                                        <div className="text-sm text-muted-foreground">
                                            {selectedRecap.period?.academic_year?.name} - {selectedRecap.period?.semester?.name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">Diajukan oleh {selectedRecap.submitter?.name ?? '-'}</div>
                                    </div>
                                    <Badge variant={selectedRecap.status === 'approved' ? 'default' : selectedRecap.status === 'rejected' ? 'destructive' : 'secondary'}>
                                        {statusLabel(selectedRecap.status)}
                                    </Badge>
                                </div>

                                <div className="max-h-[520px] overflow-auto rounded-md border">
                                    <table className="w-full text-xs">
                                        <thead className="sticky top-0 border-b bg-muted">
                                            <tr>
                                                <th className="px-2 py-2 text-left">Santri</th>
                                                <th className="px-2 py-2 text-center">Mapel</th>
                                                <th className="px-2 py-2 text-center">Pribadi</th>
                                                <th className="px-2 py-2 text-center">Kitab</th>
                                                <th className="px-2 py-2 text-center">Total</th>
                                                <th className="px-2 py-2 text-center">Keputusan</th>
                                                <th className="px-2 py-2 text-center">Placement</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {selectedRecap.items.map((item) => (
                                                <tr key={item.id} className="border-b last:border-0">
                                                    <td className="px-2 py-2">
                                                        <div className="font-medium">{item.student?.full_name}</div>
                                                        <div className="text-muted-foreground">{item.student?.nis}</div>
                                                    </td>
                                                    <td className="px-2 py-2 text-center">{numberValue(item.academic_average)}</td>
                                                    <td className="px-2 py-2 text-center">{numberValue(item.personality_score)}</td>
                                                    <td className="px-2 py-2 text-center">{numberValue(item.kitab_reading_score)}</td>
                                                    <td className="px-2 py-2 text-center font-semibold">{numberValue(item.weighted_total)}</td>
                                                    <td className="px-2 py-2 text-center">{decisionLabel(item.final_decision)}</td>
                                                    <td className="px-2 py-2 text-center">
                                                        {item.applied_class?.name ?? item.target_class?.name ?? '-'}
                                                        {item.placement_status === 'blocked' && (
                                                            <div className="text-destructive">{item.placement_message}</div>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {selectedRecap.status === 'submitted' && (
                                    <div className="grid gap-3 border-t pt-4">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Button type="button" onClick={() => approveRecap(selectedRecap.id)}>
                                                <CheckCircle2 className="mr-1 size-4" />
                                                Setujui & Proses
                                            </Button>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label className="text-xs">Catatan Penolakan</Label>
                                            <textarea
                                                className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm"
                                                value={rejectForm.data.rejection_notes}
                                                onChange={(event) => rejectForm.setData('rejection_notes', event.target.value)}
                                                placeholder="Wajib diisi jika rekap ditolak"
                                            />
                                            <div className="flex justify-end">
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    disabled={rejectForm.processing || !rejectForm.data.rejection_notes}
                                                    onClick={() => rejectRecap(selectedRecap.id)}
                                                >
                                                    <XCircle className="mr-1 size-4" />
                                                    Tolak Rekap
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
