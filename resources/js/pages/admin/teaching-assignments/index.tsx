import { Head, router } from '@inertiajs/react';
import {
    Eraser,
    Loader2,
    Search,
    UserPlus,
    Users,
    BookOpen,
    School,
    Calendar,
    CheckCircle2,
    XCircle,
    ChevronDown,
    GraduationCap,
    LayoutGrid,
    RefreshCw,
    X,
} from 'lucide-react';
import { useMemo, useState, useEffect, useRef } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import type {
    AcademicYear,
    BreadcrumbItem,
    SchoolClass,
    Semester,
    Subject,
    TeacherAssignment,
    User,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Penugasan Guru', href: '/admin/teaching-assignments' },
];

type Props = {
    assignments: TeacherAssignment[];
    teachers: Pick<User, 'id' | 'name'>[];
    activeAcademicYear: Pick<AcademicYear, 'id' | 'name'> | null;
    classes: Pick<SchoolClass, 'id' | 'name'>[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
};

type ToastState = { message: string; type: 'success' | 'error' } | null;

// ─── Toast Component ──────────────────────────────────────────────────────────
function Toast({ toast, onClose }: { toast: ToastState; onClose: () => void }) {
    if (!toast) return null;
    const isSuccess = toast.type === 'success';
    return (
        <div
            className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-5 py-4 shadow-2xl border
                transition-all duration-300 animate-in slide-in-from-bottom-4
                ${isSuccess
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-red-50 border-red-200 text-red-800'
                }`}
        >
            {isSuccess
                ? <CheckCircle2 className="h-5 w-5 text-emerald-500 shrink-0" />
                : <XCircle className="h-5 w-5 text-red-500 shrink-0" />
            }
            <span className="text-sm font-medium">{toast.message}</span>
            <button
                onClick={onClose}
                className="ml-2 rounded-full p-0.5 hover:bg-black/10 transition-colors"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}

// ─── Stat Card ────────────────────────────────────────────────────────────────
function StatCard({
    icon: Icon,
    label,
    value,
    color,
}: {
    icon: React.ElementType;
    label: string;
    value: number | string;
    color: string;
}) {
    return (
        <div className={`flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm ${color}`}>
            <div className="rounded-lg bg-muted p-2">
                <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground truncate">{label}</p>
                <p className="text-xl font-bold leading-tight">{value}</p>
            </div>
        </div>
    );
}

// ─── Cell Content ─────────────────────────────────────────────────────────────
function CellContent({
    assignment,
    isBusy,
    onAssign,
    onRemove,
    hasTeacherSelected,
}: {
    assignment?: TeacherAssignment;
    isBusy: boolean;
    onAssign: () => void;
    onRemove: (a: TeacherAssignment) => void;
    hasTeacherSelected: boolean;
}) {
    if (isBusy) {
        return (
            <div className="flex h-[72px] w-full items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30">
                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                <span className="text-xs text-muted-foreground">Memproses...</span>
            </div>
        );
    }

    if (assignment) {
        return (
            <div className="group relative flex flex-col gap-1 rounded-lg border border-emerald-200 bg-emerald-50 p-2.5 transition-all hover:shadow-sm dark:border-emerald-900 dark:bg-emerald-950/30">
                <div className="flex items-start gap-1.5">
                    <div className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500" />
                    <span className="text-xs font-semibold leading-tight text-foreground line-clamp-2">
                        {assignment.teacher?.name ?? '-'}
                    </span>
                </div>
                <div className="flex items-center gap-1">
                    <Badge
                        variant="outline"
                        className="border-emerald-300 text-[10px] text-emerald-700 px-1.5 py-0 dark:border-emerald-800 dark:text-emerald-400"
                    >
                        {assignment.target_jam} jam/mgg
                    </Badge>
                </div>
                <div className="absolute inset-0 flex items-center justify-center gap-1 rounded-lg opacity-0 transition-opacity group-hover:opacity-100 bg-card/80 dark:bg-black/60 backdrop-blur-[2px]">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    size="sm"
                                    variant="default"
                                    className="h-7 gap-1 text-xs"
                                    onClick={onAssign}
                                    onMouseDown={(e) => e.stopPropagation()}
                                    disabled={!hasTeacherSelected}
                                >
                                    <RefreshCw className="h-3 w-3" />
                                    Ganti
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {hasTeacherSelected ? 'Ganti guru' : 'Pilih guru dulu'}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <Button
                        size="sm"
                        variant="destructive"
                        className="h-7 gap-1 text-xs"
                        onClick={() => onRemove(assignment)}
                        onMouseDown={(e) => e.stopPropagation()}
                    >
                        <Eraser className="h-3 w-3" />
                        Hapus
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        onClick={onAssign}
                        className={`flex h-[72px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed
                            transition-all text-muted-foreground
                            ${hasTeacherSelected
                                ? 'hover:border-primary hover:bg-primary/5 hover:text-primary cursor-pointer'
                                : 'opacity-50 cursor-not-allowed'
                            }`}
                        disabled={!hasTeacherSelected}
                    >
                        <UserPlus className="h-4 w-4" />
                        <span className="text-[10px]">Kosong</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent>
                    {hasTeacherSelected ? 'Klik untuk assign guru' : 'Pilih guru terlebih dahulu'}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

// ─── Matrix Table ─────────────────────────────────────────────────────────────
function AssignmentMatrixTable({
    filteredClasses,
    filteredSubjects,
    assignmentMap,
    busyKey,
    onAssign,
    onRemove,
    hasTeacherSelected,
    onDragStartCell,
    onDragEnterCell,
    onDragEnd,
}: {
    filteredClasses: Pick<SchoolClass, 'id' | 'name'>[];
    filteredSubjects: Pick<Subject, 'id' | 'name'>[];
    assignmentMap: Map<string, TeacherAssignment>;
    busyKey: string | null;
    onAssign: (classId: number, subjectId: number) => void;
    onRemove: (assignment: TeacherAssignment) => void;
    hasTeacherSelected: boolean;
    onDragStartCell: (classId: number, subjectId: number) => void;
    onDragEnterCell: (classId: number, subjectId: number) => void;
    onDragEnd: () => void;
}) {
    if (filteredSubjects.length === 0 || filteredClasses.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 py-20 text-muted-foreground">
                <Search className="h-10 w-10 opacity-30" />
                <p className="text-sm">Tidak ada data yang cocok dengan pencarian.</p>
            </div>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow className="bg-muted/50">
                    <TableHead className="sticky left-0 z-20 min-w-[180px] bg-muted/80 backdrop-blur-sm font-semibold text-xs uppercase tracking-wider">
                        Mata Pelajaran
                    </TableHead>
                    {filteredClasses.map((cls) => (
                        <TableHead key={cls.id} className="min-w-[160px] text-center text-xs font-semibold uppercase tracking-wider">
                            <div className="flex items-center justify-center gap-1.5">
                                <School className="h-3.5 w-3.5 text-muted-foreground" />
                                {cls.name}
                            </div>
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {filteredSubjects.map((subject) => (
                    <TableRow key={subject.id} className="hover:bg-muted/20">
                        <TableCell className="sticky left-0 z-10 bg-background border-r">
                            <div className="flex items-center gap-2 py-0.5">
                                <div className="flex h-7 w-7 items-center justify-center rounded-md bg-primary/10 shrink-0">
                                    <BookOpen className="h-3.5 w-3.5 text-primary" />
                                </div>
                                <span className="text-sm font-medium leading-tight">{subject.name}</span>
                            </div>
                        </TableCell>
                        {filteredClasses.map((cls) => {
                            const key = `${subject.id}:${cls.id}`;
                            const assignment = assignmentMap.get(key);
                            const isBusy = busyKey === key;

                            return (
                                <TableCell
                                    key={key}
                                    className={`p-2 align-top ${hasTeacherSelected ? 'cursor-crosshair select-none' : ''}`}
                                    onMouseDown={(e) => {
                                        if (!hasTeacherSelected) return;
                                        e.preventDefault();
                                        onDragStartCell(cls.id, subject.id);
                                    }}
                                    onMouseEnter={() => {
                                        if (!hasTeacherSelected) return;
                                        onDragEnterCell(cls.id, subject.id);
                                    }}
                                    onMouseUp={() => {
                                        if (!hasTeacherSelected) return;
                                        onDragEnd();
                                    }}
                                >
                                    <CellContent
                                        assignment={assignment}
                                        isBusy={isBusy}
                                        onAssign={() => onAssign(cls.id, subject.id)}
                                        onRemove={onRemove}
                                        hasTeacherSelected={hasTeacherSelected}
                                    />
                                </TableCell>
                            );
                        })}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function TeachingAssignmentIndex({
    assignments,
    teachers,
    classes,
    subjects,
    semesters,
    activeAcademicYear,
    selectedPeriodId,
    selectedSemesterId,
}: Props) {
    const academicYearLabel = activeAcademicYear?.name ?? 'Belum ditentukan';

    const [selectedTeacherId, setSelectedTeacherId] = useState('');
    const [semesterId, setSemesterId] = useState(String(selectedSemesterId));
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [targetJam, setTargetJam] = useState('1');
    const [searchSubject, setSearchSubject] = useState('');
    const [searchClass, setSearchClass] = useState('');
    const [toast, setToast] = useState<ToastState>(null);
    const [isBulkAssigning, setIsBulkAssigning] = useState(false);
    const dragActiveRef = useRef(false);
    const dragVisitedRef = useRef<Set<string>>(new Set());
    const bulkQueueRef = useRef<Array<{ classId: number; subjectId: number }>>([]);
    const bulkProcessingRef = useRef(false);
    const bulkStatsRef = useRef({ success: 0, failed: 0, skipped: 0 });

    const defaultPeriodId = useMemo(() => {
        const active = semesters.find((p) => p.is_active);
        return String(active?.id ?? semesters[0]?.id ?? '');
    }, [semesters]);

    const filteredClasses = useMemo(() =>
        !searchClass ? classes : classes.filter(c =>
            c.name.toLowerCase().includes(searchClass.toLowerCase())
        ), [classes, searchClass]);

    const filteredSubjects = useMemo(() =>
        !searchSubject ? subjects : subjects.filter(s =>
            s.name.toLowerCase().includes(searchSubject.toLowerCase())
        ), [subjects, searchSubject]);

    const assignmentMap = useMemo(() => {
        const map = new Map<string, TeacherAssignment>();
        assignments.forEach((a) => map.set(`${a.subject_id}:${a.class_id}`, a));
        return map;
    }, [assignments]);

    const selectedTeacher = teachers.find(t => String(t.id) === selectedTeacherId);
    const totalAssignments = assignments.length;
    const uniqueTeachers = new Set(assignments.map(a => a.teacher_id)).size;
    const totalCells = classes.length * subjects.length;
    const fillRate = totalCells > 0 ? Math.round((totalAssignments / totalCells) * 100) : 0;

    useEffect(() => {
        if (toast) {
            const t = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(t);
        }
    }, [toast]);

    useEffect(() => {
        const endDrag = () => {
            dragActiveRef.current = false;
        };
        window.addEventListener('mouseup', endDrag);
        return () => window.removeEventListener('mouseup', endDrag);
    }, []);

    function refreshByPeriod(nextPeriodId: string) {
        setIsRefreshing(true);
        router.get('/admin/teaching-assignments', { semester_id: nextPeriodId || defaultPeriodId }, {
            preserveScroll: true,
            onFinish: () => setIsRefreshing(false),
        });
    }

    function assignTeacher(classId: number, subjectId: number) {
        if (!selectedTeacherId) {
            setToast({ message: 'Pilih guru terlebih dahulu sebelum assign.', type: 'error' });
            return;
        }
        const parsedTargetJam = Number(targetJam);
        if (!Number.isInteger(parsedTargetJam) || parsedTargetJam < 1) {
            setToast({ message: 'Target jam wajib angka minimal 1.', type: 'error' });
            return;
        }

        const key = `${subjectId}:${classId}`;
        setBusyKey(key);

        router.post('/admin/teaching-assignments', {
            teacher_id: selectedTeacherId,
            class_id: classId,
            subject_id: subjectId,
            semester_id: semesterId || defaultPeriodId,
            target_jam: parsedTargetJam,
        }, {
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
            onSuccess: () => setToast({ message: 'Berhasil menugaskan guru.', type: 'success' }),
            onError: () => setToast({ message: 'Gagal menugaskan guru.', type: 'error' }),
        });
    }

    function queueBulkAssign(classId: number, subjectId: number) {
        if (!selectedTeacherId) return;
        const parsedTargetJam = Number(targetJam);
        if (!Number.isInteger(parsedTargetJam) || parsedTargetJam < 1) return;

        const key = `${subjectId}:${classId}`;
        if (dragVisitedRef.current.has(key)) return;
        dragVisitedRef.current.add(key);

        const current = assignmentMap.get(key);
        const selectedTeacherNum = Number(selectedTeacherId);
        if (
            current &&
            current.teacher_id === selectedTeacherNum &&
            Number(current.target_jam) === parsedTargetJam
        ) {
            bulkStatsRef.current.skipped += 1;
            return;
        }

        bulkQueueRef.current.push({ classId, subjectId });
        processBulkQueue();
    }

    function processBulkQueue() {
        if (bulkProcessingRef.current) return;
        if (bulkQueueRef.current.length === 0) {
            if (isBulkAssigning) {
                const { success, failed, skipped } = bulkStatsRef.current;
                setIsBulkAssigning(false);
                const message = `Bulk assign selesai: ${success} berhasil, ${failed} gagal, ${skipped} dilewati.`;
                setToast({ message, type: failed > 0 ? 'error' : 'success' });
            }
            return;
        }

        const item = bulkQueueRef.current.shift()!;
        bulkProcessingRef.current = true;
        setBusyKey(`${item.subjectId}:${item.classId}`);

        router.post('/admin/teaching-assignments', {
            teacher_id: selectedTeacherId,
            class_id: item.classId,
            subject_id: item.subjectId,
            semester_id: semesterId || defaultPeriodId,
            target_jam: Number(targetJam),
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                bulkStatsRef.current.success += 1;
            },
            onError: () => {
                bulkStatsRef.current.failed += 1;
            },
            onFinish: () => {
                setBusyKey(null);
                bulkProcessingRef.current = false;
                processBulkQueue();
            },
        });
    }

    function handleDragStartCell(classId: number, subjectId: number) {
        if (!selectedTeacherId) return;
        if (isBulkAssigning) return;
        const parsedTargetJam = Number(targetJam);
        if (!Number.isInteger(parsedTargetJam) || parsedTargetJam < 1) {
            setToast({ message: 'Target jam wajib angka minimal 1.', type: 'error' });
            return;
        }
        dragActiveRef.current = true;
        setIsBulkAssigning(true);
        dragVisitedRef.current = new Set();
        bulkStatsRef.current = { success: 0, failed: 0, skipped: 0 };
        queueBulkAssign(classId, subjectId);
    }

    function handleDragEnterCell(classId: number, subjectId: number) {
        if (!dragActiveRef.current || !selectedTeacherId || isBulkAssigning === false) return;
        queueBulkAssign(classId, subjectId);
    }

    function handleDragEnd() {
        dragActiveRef.current = false;
        if (!isBulkAssigning) return;
        // If queue already drained, this will flush summary immediately.
        processBulkQueue();
    }

    function removeAssignment(assignment: TeacherAssignment) {
        if (!confirm(`Hapus penugasan ${assignment.teacher?.name} dari mata pelajaran ini?`)) return;

        const key = `${assignment.subject_id}:${assignment.class_id}`;
        setBusyKey(key);

        router.delete(`/admin/teaching-assignments/${assignment.id}`, {
            data: { semester_id: semesterId || defaultPeriodId },
            preserveScroll: true,
            onFinish: () => setBusyKey(null),
            onSuccess: () => setToast({ message: 'Penugasan berhasil dihapus.', type: 'success' }),
            onError: () => setToast({ message: 'Gagal menghapus penugasan.', type: 'error' }),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penugasan Guru" />
            <Toast toast={toast} onClose={() => setToast(null)} />

            <div className="flex h-full flex-1 flex-col gap-5 p-6">

                {/* ── Page Header ── */}
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

                    {/* Stat Pills */}
                    <div className="flex flex-wrap gap-2 sm:flex-nowrap">
                        <StatCard icon={LayoutGrid} label="Total Penugasan" value={totalAssignments} color="" />
                        <StatCard icon={Users} label="Guru Terlibat" value={uniqueTeachers} color="" />
                        <StatCard icon={BookOpen} label="Terisi" value={`${fillRate}%`} color="" />
                    </div>
                </div>

                <FlashMessage />

                {/* ── Control Panel ── */}
                <div className="grid gap-4 lg:grid-cols-3">

                    {/* Period Selector */}
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
                                onValueChange={(v) => { setSemesterId(v); refreshByPeriod(v); }}
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

                    {/* Teacher & Jam Selector */}
                    <Card className={`shadow-sm transition-all lg:col-span-2 ${selectedTeacher ? 'ring-2 ring-primary/30' : ''}`}>
                        <CardHeader className="pb-3 pt-4 px-4">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <UserPlus className="h-4 w-4 text-muted-foreground" />
                                Guru yang Akan Ditugaskan
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
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="sm:col-span-2 space-y-1.5">
                                    <Select value={selectedTeacherId} onValueChange={setSelectedTeacherId}>
                                        <SelectTrigger className={selectedTeacher ? 'border-primary/50' : ''}>
                                            <SelectValue placeholder="Pilih guru..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {teachers.map((t) => (
                                                <SelectItem key={t.id} value={String(t.id)}>
                                                    {t.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Klik sel kosong untuk assign · Klik dan geser untuk bulk assign
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs text-muted-foreground">Target Jam/Minggu</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={targetJam}
                                        onChange={(e) => setTargetJam(e.target.value)}
                                        className="text-center font-semibold"
                                    />
                                    <p className="text-xs text-muted-foreground">Maks. slot jadwal</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ── Search Filters ── */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-[200px] flex-1 max-w-xs">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Cari mata pelajaran..."
                            value={searchSubject}
                            onChange={(e) => setSearchSubject(e.target.value)}
                            className="pl-9"
                        />
                        {searchSubject && (
                            <button
                                onClick={() => setSearchSubject('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                    <div className="relative min-w-[200px] flex-1 max-w-xs">
                        <School className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Cari kelas..."
                            value={searchClass}
                            onChange={(e) => setSearchClass(e.target.value)}
                            className="pl-9"
                        />
                        {searchClass && (
                            <button
                                onClick={() => setSearchClass('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    {(searchSubject || searchClass) && (
                        <p className="text-xs text-muted-foreground">
                            Menampilkan{' '}
                            <span className="font-medium">{filteredSubjects.length}</span> mapel ·{' '}
                            <span className="font-medium">{filteredClasses.length}</span> kelas
                        </p>
                    )}
                </div>

                {/* ── Matrix Table ── */}
                <Card className="overflow-hidden shadow-sm flex-1">
                    <CardHeader className="flex flex-row items-center justify-between border-b py-3 px-5">
                        <div>
                            <CardTitle className="text-sm font-semibold">Matriks Penugasan</CardTitle>
                            <CardDescription className="text-xs mt-0.5">
                                {filteredSubjects.length} mata pelajaran · {filteredClasses.length} kelas
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            {isBulkAssigning && (
                                <Badge variant="secondary" className="text-[10px]">
                                    Bulk assigning...
                                </Badge>
                            )}
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span className="flex h-3 w-3 rounded-full bg-emerald-500" />
                                Terisi
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span className="flex h-3 w-3 rounded-full border-2 border-dashed border-muted-foreground/40" />
                                Kosong
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <AssignmentMatrixTable
                                filteredClasses={filteredClasses}
                                filteredSubjects={filteredSubjects}
                                assignmentMap={assignmentMap}
                                busyKey={busyKey}
                                onAssign={assignTeacher}
                                onRemove={removeAssignment}
                                hasTeacherSelected={!!selectedTeacherId}
                                onDragStartCell={handleDragStartCell}
                                onDragEnterCell={handleDragEnterCell}
                                onDragEnd={handleDragEnd}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}