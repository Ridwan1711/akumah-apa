import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData } from '@/types';

type AttendanceRow = {
    id: number;
    status: 'present' | 'excused' | 'absent';
    reason: string | null;
    lesson_session: {
        id: number;
        date: string;
        start_time: string;
        end_time: string;
        schedule: {
            school_class: { id: number; name: string; grade_level_id: number | null };
            subject: { id: number; name: string };
        };
    };
};

type Props = {
    attendances: PaginatedData<AttendanceRow>;
    filters: {
        status?: string;
        date_from?: string;
        date_to?: string;
    };
};

const statusLabel: Record<string, string> = {
    present: 'Hadir',
    excused: 'Izin',
    absent: 'Alpha',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kehadiran Saya', href: '/santri/attendances' },
];

export default function SantriAttendances({ attendances, filters }: Props) {
    const [localFilters, setLocalFilters] = useState(filters);

    function applyFilters() {
        const clean = Object.fromEntries(
            Object.entries(localFilters).filter(([, value]) => value && value !== 'all'),
        );
        router.get('/santri/attendances', clean, { preserveState: true, preserveScroll: true });
    }

    function resetFilters() {
        setLocalFilters({});
        router.get('/santri/attendances', {}, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kehadiran Saya" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Kehadiran Saya</h1>
                    <p className="text-sm text-muted-foreground">Riwayat kehadiran per sesi pelajaran.</p>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4">
                    <div className="grid gap-1">
                        <Label className="text-xs">Status</Label>
                        <Select
                            value={localFilters.status ?? 'all'}
                            onValueChange={(value) => setLocalFilters({ ...localFilters, status: value === 'all' ? undefined : value })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                <SelectItem value="present">Hadir</SelectItem>
                                <SelectItem value="excused">Izin</SelectItem>
                                <SelectItem value="absent">Alpha</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Dari Tanggal</Label>
                        <Input
                            type="date"
                            className="w-44"
                            value={localFilters.date_from ?? ''}
                            onChange={(event) => setLocalFilters({ ...localFilters, date_from: event.target.value || undefined })}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Sampai Tanggal</Label>
                        <Input
                            type="date"
                            className="w-44"
                            value={localFilters.date_to ?? ''}
                            onChange={(event) => setLocalFilters({ ...localFilters, date_to: event.target.value || undefined })}
                        />
                    </div>
                    <Button type="button" size="sm" onClick={applyFilters}>Filter</Button>
                    <Button type="button" size="sm" variant="outline" onClick={resetFilters}>Reset</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Tanggal</th>
                                <th className="px-4 py-3 text-left font-medium">Jam</th>
                                <th className="px-4 py-3 text-left font-medium">Mapel</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {attendances.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                        Belum ada data kehadiran.
                                    </td>
                                </tr>
                            ) : (
                                attendances.data.map((row) => (
                                    <tr key={row.id} className="border-b last:border-0">
                                        <td className="px-4 py-3 whitespace-nowrap">{row.lesson_session.date}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {row.lesson_session.start_time} - {row.lesson_session.end_time}
                                        </td>
                                        <td className="px-4 py-3">{row.lesson_session.schedule.subject?.name ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{statusLabel[row.status] ?? row.status}</Badge>
                                        </td>
                                        <td className="px-4 py-3">{row.reason ?? '-'}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={attendances.links} from={attendances.from} to={attendances.to} total={attendances.total} />
            </div>
        </AppLayout>
    );
}
