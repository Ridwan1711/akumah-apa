import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Eye, Shuffle, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
type PlacementStatus = 'pending' | 'applied' | 'blocked';
type PlacementFilter = 'all' | 'problematic' | 'pending' | 'blocked' | 'automatic' | 'applied';

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
    placement_pending_count?: number;
    placement_applied_count?: number;
    placement_blocked_count?: number;
    automatic_target_count?: number;
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
    placement_status: PlacementStatus;
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

function placementStatusLabel(status: PlacementStatus) {
    if (status === 'applied') return 'Sudah ditempatkan';
    if (status === 'blocked') return 'Blocked';

    return 'Pending';
}

function placementBadgeVariant(status: PlacementStatus): 'default' | 'secondary' | 'destructive' {
    if (status === 'applied') return 'default';
    if (status === 'blocked') return 'destructive';

    return 'secondary';
}

function isAutomaticPlacement(item: RecapItem) {
    return item.final_decision !== 'graduate' && !item.target_class && !item.applied_class;
}

const placementFilters: { value: PlacementFilter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'problematic', label: 'Perlu Dicek' },
    { value: 'pending', label: 'Pending' },
    { value: 'blocked', label: 'Blocked' },
    { value: 'automatic', label: 'Otomatis' },
    { value: 'applied', label: 'Applied' },
];

export default function ClassPromotionIndex({ recaps, selectedRecap, filters, statusOptions }: Props) {
    const rejectForm = useForm({ rejection_notes: '' });
    const [placementFilter, setPlacementFilter] = useState<PlacementFilter>('all');

    const placementSummary = useMemo(() => {
        const items = selectedRecap?.items ?? [];

        return {
            total: items.length,
            pending: items.filter((item) => item.placement_status === 'pending').length,
            applied: items.filter((item) => item.placement_status === 'applied').length,
            blocked: items.filter((item) => item.placement_status === 'blocked').length,
            automatic: items.filter(isAutomaticPlacement).length,
        };
    }, [selectedRecap]);

    const filteredItems = useMemo(() => {
        const items = selectedRecap?.items ?? [];

        if (placementFilter === 'problematic') {
            return items.filter((item) => item.placement_status === 'blocked' || isAutomaticPlacement(item));
        }

        if (placementFilter === 'automatic') {
            return items.filter(isAutomaticPlacement);
        }

        if (placementFilter === 'all') {
            return items;
        }

        return items.filter((item) => item.placement_status === placementFilter);
    }, [placementFilter, selectedRecap]);

    function setStatus(value: string) {
        router.get('/admin/class-promotion', { status: value === 'all' ? 'all' : value }, { preserveState: true, preserveScroll: true });
    }

    function approveRecap(recapId: number) {
        if (selectedRecap?.id === recapId) {
            const warnings: string[] = [];

            if (placementSummary.blocked > 0) {
                warnings.push(`${placementSummary.blocked} santri masih berstatus blocked.`);
            }

            if (placementSummary.automatic > 0) {
                warnings.push(
                    `${placementSummary.automatic} santri belum punya kelas tujuan eksplisit dan akan memakai resolusi otomatis berdasarkan tingkat/gender.`,
                );
            }

            if (
                warnings.length > 0 &&
                !window.confirm(`Lanjut setujui dan proses rekap ini?\n\n${warnings.join('\n')}\n\nPastikan placement sudah sesuai sebelum melanjutkan.`)
            ) {
                return;
            }
        }

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
                                    <th className="px-3 py-3 text-center">Placement</th>
                                    <th className="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recaps.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
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
                                            <td className="px-3 py-2">
                                                <div className="flex flex-wrap justify-center gap-1">
                                                    {(recap.placement_blocked_count ?? 0) > 0 && (
                                                        <Badge variant="destructive">{recap.placement_blocked_count} blocked</Badge>
                                                    )}
                                                    {(recap.automatic_target_count ?? 0) > 0 && (
                                                        <Badge variant="outline" className="border-amber-300 text-amber-700">
                                                            {recap.automatic_target_count} otomatis
                                                        </Badge>
                                                    )}
                                                    {(recap.placement_pending_count ?? 0) > 0 && (
                                                        <Badge variant="secondary">{recap.placement_pending_count} pending</Badge>
                                                    )}
                                                    {(recap.placement_blocked_count ?? 0) === 0 &&
                                                        (recap.automatic_target_count ?? 0) === 0 &&
                                                        (recap.placement_pending_count ?? 0) === 0 && (
                                                            <Badge variant="outline">{recap.placement_applied_count ?? 0} applied</Badge>
                                                        )}
                                                </div>
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

                                {(placementSummary.blocked > 0 || placementSummary.automatic > 0) && selectedRecap.status === 'submitted' && (
                                    <Alert className="border-amber-300 bg-amber-50 text-amber-950">
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>Periksa placement sebelum approval</AlertTitle>
                                        <AlertDescription className="text-amber-900">
                                            {placementSummary.blocked > 0 && (
                                                <p>{placementSummary.blocked} santri berstatus blocked dan perlu ditinjau sebelum diproses ulang.</p>
                                            )}
                                            {placementSummary.automatic > 0 && (
                                                <p>
                                                    {placementSummary.automatic} santri belum memiliki kelas tujuan eksplisit. Saat disetujui, sistem akan
                                                    memilih kelas otomatis berdasarkan tingkat dan gender.
                                                </p>
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="grid gap-2 rounded-md border bg-muted/30 p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <div className="text-sm font-semibold">Ringkasan Placement</div>
                                            <div className="text-xs text-muted-foreground">Gunakan filter untuk fokus ke item yang perlu dicek.</div>
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            {placementFilters.map((option) => (
                                                <Button
                                                    key={option.value}
                                                    type="button"
                                                    size="sm"
                                                    variant={placementFilter === option.value ? 'default' : 'outline'}
                                                    className="h-7 px-2 text-xs"
                                                    onClick={() => setPlacementFilter(option.value)}
                                                >
                                                    {option.label}
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                                        <div className="rounded-md border bg-background p-2">
                                            <div className="text-muted-foreground">Pending</div>
                                            <div className="text-lg font-semibold">{placementSummary.pending}</div>
                                        </div>
                                        <div className="rounded-md border bg-background p-2">
                                            <div className="text-muted-foreground">Applied</div>
                                            <div className="text-lg font-semibold">{placementSummary.applied}</div>
                                        </div>
                                        <div className="rounded-md border bg-background p-2">
                                            <div className="text-muted-foreground">Blocked</div>
                                            <div className="text-lg font-semibold text-destructive">{placementSummary.blocked}</div>
                                        </div>
                                        <div className="rounded-md border bg-background p-2">
                                            <div className="text-muted-foreground">Otomatis</div>
                                            <div className="text-lg font-semibold text-amber-700">{placementSummary.automatic}</div>
                                        </div>
                                        <div className="rounded-md border bg-background p-2">
                                            <div className="text-muted-foreground">Ditampilkan</div>
                                            <div className="text-lg font-semibold">
                                                {filteredItems.length}/{placementSummary.total}
                                            </div>
                                        </div>
                                    </div>
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
                                            {filteredItems.length === 0 ? (
                                                <tr>
                                                    <td colSpan={7} className="px-2 py-8 text-center text-muted-foreground">
                                                        Tidak ada santri untuk filter placement ini.
                                                    </td>
                                                </tr>
                                            ) : (
                                                filteredItems.map((item) => (
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
                                                            <div className="grid justify-items-center gap-1">
                                                                <Badge variant={placementBadgeVariant(item.placement_status)}>
                                                                    {placementStatusLabel(item.placement_status)}
                                                                </Badge>
                                                                {isAutomaticPlacement(item) ? (
                                                                    <div className="inline-flex items-center gap-1 text-amber-700">
                                                                        <Shuffle className="size-3" />
                                                                        Otomatis saat approve
                                                                    </div>
                                                                ) : (
                                                                    <div>{item.applied_class?.name ?? item.target_class?.name ?? '-'}</div>
                                                                )}
                                                                {item.placement_message && (
                                                                    <div className={item.placement_status === 'blocked' ? 'text-destructive' : 'text-muted-foreground'}>
                                                                        {item.placement_message}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
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
