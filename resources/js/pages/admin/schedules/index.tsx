import { Head, router } from '@inertiajs/react';
import { CalendarClock, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    DiniyahClass,
    PaginatedData,
    SchoolClass,
    Semester,
    Subject,
    User,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jadwal Kitab', href: '/admin/schedules' },
];

const dayLabels: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Minggu',
};

type ScheduleRow = {
    id: number;
    day: number;
    time_start: string;
    time_end: string;
    school_class: Pick<DiniyahClass, 'id' | 'name' | 'grade_level_id'>;
    subject: Pick<Subject, 'id' | 'name'>;
    teacher: Pick<User, 'id' | 'name'>;
    period: { id: number; name: string; type?: string | null };
};

type Props = {
    schedules: PaginatedData<ScheduleRow>;
    classes: Pick<SchoolClass, 'id' | 'name' | 'grade_level_id'>[];
    subjects: Pick<Subject, 'id' | 'name'>[];
    teachers: Pick<User, 'id' | 'name'>[];
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
    filters: {
        class_id?: string;
        teacher_id?: string;
        day_of_week?: string;
    };
};

type FormState = {
    class_id: string;
    subject_id: string;
    teacher_id: string;
    semester_id: string;
    day: string;
    time_start: string;
    time_end: string;
};

const emptyForm = (periodId: number): FormState => ({
    class_id: '',
    subject_id: '',
    teacher_id: '',
    semester_id: String(periodId),
    day: '1',
    time_start: '07:00',
    time_end: '08:00',
});

export default function SchedulesIndex({
    schedules,
    classes,
    subjects,
    teachers,
    semesters,
    selectedPeriodId,
    selectedSemesterId,
    filters,
}: Props) {
    const [localFilters, setLocalFilters] = useState(filters);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<ScheduleRow | null>(null);
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<FormState>(() => emptyForm(selectedPeriodId));

    const periodQuery = useMemo(
        () => (selectedSemesterId > 0 ? { semester_id: selectedSemesterId } : {}),
        [selectedSemesterId],
    );

    function applyFilters() {
        const clean = Object.fromEntries(
            Object.entries({ ...periodQuery, ...localFilters }).filter(([, v]) => v && v !== 'all'),
        );
        router.get('/admin/schedules', clean, { preserveState: true, preserveScroll: true });
    }

    function resetFilters() {
        setLocalFilters({});
        router.get('/admin/schedules', periodQuery, { preserveState: true });
    }

    function changePeriod(periodId: string) {
        router.get('/admin/schedules', { semester_id: periodId }, { preserveState: true });
    }

    function openCreate() {
        setEditing(null);
        setForm(emptyForm(selectedPeriodId));
        setDialogOpen(true);
    }

    function openEdit(row: ScheduleRow) {
        setEditing(row);
        setForm({
            class_id: String(row.school_class.id),
            subject_id: String(row.subject.id),
            teacher_id: String(row.teacher.id),
            semester_id: String(selectedSemesterId),
            day: String(row.day),
            time_start: row.time_start.slice(0, 5),
            time_end: row.time_end.slice(0, 5),
        });
        setDialogOpen(true);
    }

    function submitForm() {
        setBusy(true);
        const payload = {
            class_id: Number(form.class_id),
            subject_id: Number(form.subject_id),
            teacher_id: Number(form.teacher_id),
            semester_id: Number(form.semester_id),
            day: Number(form.day),
            time_start: form.time_start,
            time_end: form.time_end,
        };

        if (editing) {
            router.put(`/admin/schedules/${editing.id}`, payload, {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => {
                    setDialogOpen(false);
                    setEditing(null);
                },
            });
        } else {
            router.post('/admin/schedules', payload, {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => {
                    setDialogOpen(false);
                },
            });
        }
    }

    function destroyRow(row: ScheduleRow) {
        if (!confirm(`Hapus jadwal ${row.subject.name} — ${row.school_class.name}?`)) {
            return;
        }
        const qs = new URLSearchParams({ semester_id: String(selectedSemesterId) });
        router.delete(`/admin/schedules/${row.id}?${qs.toString()}`, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal Kitab" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <FlashMessage />
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Jadwal Kitab"
                        description="Atur jadwal mengajar per periode akademik"
                    />
                    <Button onClick={openCreate} disabled={semesters.length === 0}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah jadwal
                    </Button>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label className="text-xs">Periode</Label>
                        <Select
                            value={selectedSemesterId > 0 ? String(selectedSemesterId) : ''}
                            onValueChange={changePeriod}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Pilih periode" />
                            </SelectTrigger>
                            <SelectContent>
                                {semesters.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name}
                                        {p.is_active ? ' (aktif)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Kelas</Label>
                        <Select
                            value={localFilters.class_id ?? 'all'}
                            onValueChange={(v) =>
                                setLocalFilters({ ...localFilters, class_id: v === 'all' ? undefined : v })
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Semua kelas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                {classes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Guru</Label>
                        <Select
                            value={localFilters.teacher_id ?? 'all'}
                            onValueChange={(v) =>
                                setLocalFilters({ ...localFilters, teacher_id: v === 'all' ? undefined : v })
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Semua guru" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                {teachers.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1">
                        <Label className="text-xs">Hari</Label>
                        <Select
                            value={localFilters.day_of_week ?? 'all'}
                            onValueChange={(v) =>
                                setLocalFilters({ ...localFilters, day_of_week: v === 'all' ? undefined : v })
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Semua" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                {Object.entries(dayLabels).map(([k, label]) => (
                                    <SelectItem key={k} value={k}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button size="sm" type="button" onClick={applyFilters}>
                        Filter
                    </Button>
                    <Button size="sm" type="button" variant="outline" onClick={resetFilters}>
                        Reset
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Hari</TableHead>
                                <TableHead>Jam</TableHead>
                                <TableHead>Kelas</TableHead>
                                <TableHead>Mapel</TableHead>
                                <TableHead>Guru</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {schedules.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-10 text-center text-muted-foreground">
                                        Belum ada jadwal untuk filter ini.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                schedules.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {dayLabels[row.day] ?? row.day}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {row.time_start.slice(0, 5)} — {row.time_end.slice(0, 5)}
                                        </TableCell>
                                        <TableCell>{row.school_class.name}</TableCell>
                                        <TableCell>{row.subject.name}</TableCell>
                                        <TableCell>{row.teacher.name}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() => openEdit(row)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    onClick={() => destroyRow(row)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <Pagination
                    links={schedules.links}
                    from={schedules.from}
                    to={schedules.to}
                    total={schedules.total}
                />

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <CalendarClock className="h-5 w-5" />
                                {editing ? 'Ubah jadwal' : 'Jadwal baru'}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-3 py-2">
                            <div className="grid gap-1">
                                <Label>Periode akademik</Label>
                                <Select
                                    value={form.semester_id}
                                    onValueChange={(v) => setForm({ ...form, semester_id: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Periode" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Kelas</Label>
                                <Select
                                    value={form.class_id}
                                    onValueChange={(v) => setForm({ ...form, class_id: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classes.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Mata pelajaran</Label>
                                <Select
                                    value={form.subject_id}
                                    onValueChange={(v) => setForm({ ...form, subject_id: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih mapel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {subjects.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Guru</Label>
                                <Select
                                    value={form.teacher_id}
                                    onValueChange={(v) => setForm({ ...form, teacher_id: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih guru" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {teachers.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label>Hari</Label>
                                <Select value={form.day} onValueChange={(v) => setForm({ ...form, day: v })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(dayLabels).map(([k, label]) => (
                                            <SelectItem key={k} value={k}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label>Mulai</Label>
                                    <Input
                                        type="time"
                                        value={form.time_start}
                                        onChange={(e) => setForm({ ...form, time_start: e.target.value })}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label>Selesai</Label>
                                    <Input
                                        type="time"
                                        value={form.time_end}
                                        onChange={(e) => setForm({ ...form, time_end: e.target.value })}
                                    />
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Batal
                            </Button>
                            <Button
                                type="button"
                                onClick={submitForm}
                                disabled={
                                    busy ||
                                    !form.class_id ||
                                    !form.subject_id ||
                                    !form.teacher_id ||
                                    !form.semester_id
                                }
                            >
                                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
