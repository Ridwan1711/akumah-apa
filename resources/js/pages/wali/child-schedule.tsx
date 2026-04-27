import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ScheduleEntry = {
    id: number;
    subject: { id: number | null; name: string | null };
    teacher: { id: number | null; name: string | null };
    start_time: string;
    end_time: string;
    room: string | null;
};

type ScheduleDay = {
    day_of_week: number;
    day_name: string;
    entries: ScheduleEntry[];
};

type Props = {
    student: { id: number; full_name: string; nis: string };
    class: { id: number; name: string; level: string | null } | null;
    semester: { id: number; name: string } | null;
    week: ScheduleDay[];
};

export default function WaliChildSchedule({ student, class: schoolClass, semester, week }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Anak', href: '/wali/children' },
        { title: student.full_name, href: `/wali/children/${student.id}` },
        { title: 'Jadwal Anak', href: `/wali/children/${student.id}/schedule` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal Anak" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-8">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Jadwal Anak</h1>
                        <p className="text-sm text-muted-foreground">
                            {student.full_name} ({student.nis}){schoolClass ? ` - ${schoolClass.name}` : ''}{semester ? ` - ${semester.name}` : ''}.
                        </p>
                    </div>
                    <Link
                        href={`/wali/children/${student.id}`}
                        className="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        Kembali ke Detail
                    </Link>
                </div>

                {week.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Belum ada jadwal pelajaran untuk anak ini.
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {week.map((day) => (
                            <section key={day.day_of_week} className="overflow-hidden rounded-lg border bg-white">
                                <div className="border-b bg-muted/30 px-4 py-3 font-medium">
                                    {day.day_name}
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b bg-muted/20">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-medium">Jam</th>
                                                <th className="px-4 py-3 text-left font-medium">Mata Pelajaran</th>
                                                <th className="px-4 py-3 text-left font-medium">Guru</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {day.entries.map((entry) => (
                                                <tr key={entry.id} className="border-b last:border-0">
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {entry.start_time} - {entry.end_time}
                                                    </td>
                                                    <td className="px-4 py-3">{entry.subject.name ?? '-'}</td>
                                                    <td className="px-4 py-3">{entry.teacher.name ?? '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
