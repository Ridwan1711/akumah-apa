import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ScheduleEntry = {
    id: number;
    class: { id: number | null; name: string | null; grade_level_id: number | null };
    subject: { id: number | null; name: string | null };
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
    teacher: { id: number; name: string };
    semester: { id: number; name: string } | null;
    week: ScheduleDay[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jadwal Guru', href: '/admin/schedule' },
];

export default function GuruSchedule({ teacher, semester, week }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal Guru" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">Jadwal Guru</h1>
                    <p className="text-sm text-muted-foreground">
                        Jadwal mengajar {teacher.name}{semester ? ` - ${semester.name}` : ''}.
                    </p>
                </div>

                {week.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Belum ada jadwal mengajar.
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
                                                <th className="px-4 py-3 text-left font-medium">Kelas</th>
                                                <th className="px-4 py-3 text-left font-medium">Mata Pelajaran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {day.entries.map((entry) => (
                                                <tr key={entry.id} className="border-b last:border-0">
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {entry.start_time} - {entry.end_time}
                                                    </td>
                                                    <td className="px-4 py-3">{entry.class.name ?? '-'}</td>
                                                    <td className="px-4 py-3">{entry.subject.name ?? '-'}</td>
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
