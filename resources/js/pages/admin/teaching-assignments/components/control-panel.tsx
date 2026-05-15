import { Calendar, CheckCircle2, Loader2, UserPlus } from 'lucide-react';
import { AppSelect } from '@/components/manhood';
import type { SelectOption } from '@/components/manhood';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { User } from '@/types';
import type { TeachingAssignmentPageProps } from '../types';

type SemesterOption = TeachingAssignmentPageProps['semesters'][number];

type ControlPanelProps = {
    semesters: SemesterOption[];
    semesterId: string;
    defaultPeriodId: string;
    isRefreshing: boolean;
    onSemesterChange: (value: string) => void;
    teacherSelectOptions: SelectOption[];
    selectedTeacherOption: SelectOption | null;
    onTeacherChange: (teacherId: string) => void;
    selectedTeacher: Pick<User, 'id' | 'name'> | undefined;
};

export function ControlPanel({
    semesters,
    semesterId,
    defaultPeriodId,
    isRefreshing,
    onSemesterChange,
    teacherSelectOptions,
    selectedTeacherOption,
    onTeacherChange,
    selectedTeacher,
}: ControlPanelProps) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card className="shadow-sm">
                <CardHeader className="pb-3 pt-4 px-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                        <Calendar className="h-4 w-4 text-muted-foreground" />
                        Periode Akademik
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-4 pb-4 space-y-2">
                    <Select
                        value={semesterId || defaultPeriodId}
                        onValueChange={onSemesterChange}
                        disabled={isRefreshing}
                    >
                        <SelectTrigger className="w-full">
                            {isRefreshing
                                ? <span className="flex items-center gap-2 text-muted-foreground">
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    Memuat...
                                  </span>
                                : <SelectValue placeholder="Pilih periode" />
                            }
                        </SelectTrigger>
                        <SelectContent>
                            {semesters.map((period) => (
                                <SelectItem key={period.id} value={String(period.id)}>
                                    <span className="flex items-center gap-2">
                                        {period.name}
                                        {period.is_active && (
                                            <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                                                Aktif
                                            </Badge>
                                        )}
                                    </span>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </CardContent>
            </Card>

            <Card className={`shadow-sm transition-all lg:col-span-2 ${selectedTeacher ? 'ring-2 ring-primary/30' : ''}`}>
                <CardHeader className="pb-3 pt-4 px-4">
                    <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                        <UserPlus className="h-4 w-4 text-muted-foreground" />
                        Guru dari Master Guru
                        {!selectedTeacher && (
                            <Badge variant="outline" className="ml-auto text-[10px] text-amber-600 border-amber-300 bg-amber-50">
                                Wajib dipilih sebelum assign
                            </Badge>
                        )}
                        {selectedTeacher && (
                            <Badge variant="secondary" className="ml-auto text-[10px] gap-1">
                                <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                                Siap assign
                            </Badge>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-4 pb-4">
                    <div className="space-y-1.5">
                        <AppSelect
                            inputId="teaching-assignments-teacher"
                            placeholder="Pilih guru..."
                            options={teacherSelectOptions}
                            value={selectedTeacherOption}
                            onChange={(opt) => onTeacherChange(opt ? String(opt.value) : '')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Sumber guru hanya dari halaman Manajemen Guru · target jam otomatis dari setting default/override
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
