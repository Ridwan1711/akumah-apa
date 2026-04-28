import { Head, Link, useForm } from '@inertiajs/react';
import FlashMessage from '@/components/flash-message';
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
import type { BreadcrumbItem } from '@/types';

type StudentAttendance = {
    id: number;
    nis: string;
    full_name: string;
    attendance: {
        id: number;
        status: 'present' | 'excused' | 'absent';
        reason: string | null;
    } | null;
};

type Props = {
    session: {
        id: number;
        date: string;
        start_time: string;
        end_time: string;
        status: string;
        class: { id: number; name: string; grade_level_id: number | null };
        subject: { id: number; name: string };
    };
    students: StudentAttendance[];
};

type AttendanceInput = {
    student_id: number;
    status: 'present' | 'excused' | 'absent';
    reason: string;
};

type FormShape = {
    attendances: AttendanceInput[];
};

export default function GuruAttendanceSessionShow({ session, students }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Absensi Siswa', href: '/admin/attendance-sessions' },
        { title: 'Detail Sesi', href: `/admin/attendance-sessions/${session.id}` },
    ];

    const { data, setData, post, processing } = useForm<FormShape>({
        attendances: students.map((student) => ({
            student_id: student.id,
            status: student.attendance?.status ?? 'present',
            reason: student.attendance?.reason ?? '',
        })),
    });

    function setStatus(index: number, value: 'present' | 'excused' | 'absent') {
        const next = [...data.attendances];
        next[index] = { ...next[index], status: value };
        if (value === 'present') {
            next[index].reason = '';
        }
        setData('attendances', next);
    }

    function setReason(index: number, value: string) {
        const next = [...data.attendances];
        next[index] = { ...next[index], reason: value };
        setData('attendances', next);
    }

    function submit() {
        post(`/admin/attendance-sessions/${session.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Input Absensi Siswa" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-8">
                <FlashMessage />
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Input Absensi Siswa</h1>
                        <p className="text-sm text-muted-foreground">
                            {session.date} - {session.start_time} s/d {session.end_time} | {session.class.name} | {session.subject.name}
                        </p>
                    </div>
                    <Link href="/admin/attendance-sessions">
                        <Button type="button" variant="outline">Kembali ke daftar sesi</Button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/30">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Santri</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center text-muted-foreground">
                                        Tidak ada santri aktif di kelas ini.
                                    </td>
                                </tr>
                            ) : (
                                students.map((student, index) => {
                                    const row = data.attendances[index];
                                    const needReason = row.status !== 'present';
                                    return (
                                        <tr key={student.id} className="border-b last:border-0">
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{student.full_name}</div>
                                                <div className="text-xs text-muted-foreground">{student.nis}</div>
                                            </td>
                                            <td className="px-4 py-3 w-52">
                                                <Select value={row.status} onValueChange={(value) => setStatus(index, value as AttendanceInput['status'])}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="present">Hadir</SelectItem>
                                                        <SelectItem value="excused">Izin</SelectItem>
                                                        <SelectItem value="absent">Alpha</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Label className="sr-only">Keterangan</Label>
                                                <Input
                                                    value={row.reason}
                                                    onChange={(e) => setReason(index, e.target.value)}
                                                    placeholder={needReason ? 'Wajib diisi jika izin/alpha' : 'Opsional'}
                                                    disabled={!needReason}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <Button type="button" onClick={submit} disabled={processing || students.length === 0}>
                        {processing ? 'Menyimpan...' : 'Simpan Kehadiran'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
