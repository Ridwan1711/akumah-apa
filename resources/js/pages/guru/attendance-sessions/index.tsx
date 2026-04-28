import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type SessionRow = {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    class: { id: number | null; name: string | null; grade_level_id: number | null };
    subject: { id: number | null; name: string | null };
};

type Props = {
    date: string;
    semester: { id: number; name: string } | null;
    sessions: SessionRow[];
    filters: { date?: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Absensi Siswa', href: '/admin/attendance-sessions' },
];

export default function GuruAttendanceSessionsIndex({ date, semester, sessions, filters }: Props) {
    const [selectedDate, setSelectedDate] = useState(filters.date ?? date);

    function applyDateFilter() {
        router.get('/admin/attendance-sessions', { date: selectedDate }, { preserveState: true, preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Absensi Siswa" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Absensi Siswa</h1>
                    <p className="text-sm text-muted-foreground">
                        Daftar sesi mengajar berdasarkan jadwal Anda{semester ? ` - ${semester.name}` : ''}.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4">
                    <div className="grid gap-1">
                        <Label className="text-xs">Tanggal</Label>
                        <Input type="date" value={selectedDate} onChange={(e) => setSelectedDate(e.target.value)} className="w-48" />
                    </div>
                    <Button type="button" onClick={applyDateFilter}>Tampilkan Sesi</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/30">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Jam</th>
                                <th className="px-4 py-3 text-left font-medium">Kelas</th>
                                <th className="px-4 py-3 text-left font-medium">Mata Pelajaran</th>
                                <th className="px-4 py-3 text-left font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                                        Tidak ada sesi mengajar untuk tanggal ini.
                                    </td>
                                </tr>
                            ) : (
                                sessions.map((session) => (
                                    <tr key={session.id} className="border-b last:border-0">
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {session.start_time} - {session.end_time}
                                        </td>
                                        <td className="px-4 py-3">{session.class.name ?? '-'}</td>
                                        <td className="px-4 py-3">{session.subject.name ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/admin/attendance-sessions/${session.id}`}
                                                className="inline-flex items-center rounded-md border px-3 py-2 text-xs font-medium hover:bg-muted"
                                            >
                                                Isi / Lihat Absensi
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
