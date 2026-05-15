import { BookOpen, GraduationCap, LayoutGrid, Users } from 'lucide-react';
import { StatCard } from './stat-card';

type PageHeaderProps = {
    academicYearLabel: string;
    totalAssignments: number;
    uniqueTeachers: number;
    fillRate: number;
};

export function PageHeader({
    academicYearLabel,
    totalAssignments,
    uniqueTeachers,
    fillRate,
}: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1">
                <div className="flex items-center gap-2">
                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                        <GraduationCap className="h-5 w-5" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">Penugasan Guru</h1>
                </div>
                <p className="text-sm text-muted-foreground pl-11">
                    Tahun Ajaran <span className="font-medium text-foreground">{academicYearLabel}</span>
                    {' · '}Kelola penugasan guru per kelas dan mata pelajaran
                </p>
            </div>

            <div className="flex flex-wrap gap-2 sm:flex-nowrap">
                <StatCard icon={LayoutGrid} label="Total Penugasan" value={totalAssignments} color="" />
                <StatCard icon={Users} label="Guru Terlibat" value={uniqueTeachers} color="" />
                <StatCard icon={BookOpen} label="Terisi" value={`${fillRate}%`} color="" />
            </div>
        </div>
    );
}
