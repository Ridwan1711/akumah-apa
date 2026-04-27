import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Copy, Edit3, Grid3x3, Loader2, Plus, Power, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem, ScheduleSet, Semester } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Jadwal (Matrix)', href: '/admin/schedule-sets' },
];

type Props = {
    sets: ScheduleSet[];
    semesters: (Pick<Semester, 'id' | 'name'> & { academic_year_name?: string | null; is_active?: boolean })[];
    selectedPeriodId: number;
    selectedSemesterId: number;
};

type FormState = {
    semester_id: string;
    name: string;
    jam_count: string;
    day_count: string;
    is_active: boolean;
    copy_from_id: string;
};

const emptyForm = (periodId: number): FormState => ({
    semester_id: periodId > 0 ? String(periodId) : '',
    name: '',
    jam_count: '6',
    day_count: '6',
    is_active: false,
    copy_from_id: '',
});

export default function ScheduleSetsIndex({ sets, semesters, selectedPeriodId, selectedSemesterId }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<ScheduleSet | null>(null);
    const [form, setForm] = useState<FormState>(() => emptyForm(selectedSemesterId));
    const [busy, setBusy] = useState(false);

    const copyCandidates = useMemo(() => sets, [sets]);

    function changePeriod(periodId: string) {
        router.get('/admin/schedule-sets', { semester_id: periodId }, { preserveState: true });
    }

    function openCreate() {
        setEditing(null);
        setForm(emptyForm(selectedPeriodId));
        setDialogOpen(true);
    }

    function openEdit(set: ScheduleSet) {
        setEditing(set);
        setForm({
            semester_id: String(selectedSemesterId),
            name: set.name,
            jam_count: String(set.jam_count),
            day_count: String(set.day_count),
            is_active: set.is_active,
            copy_from_id: '',
        });
        setDialogOpen(true);
    }

    function submit() {
        setBusy(true);
        const payload = {
            semester_id: Number(form.semester_id),
            name: form.name,
            jam_count: Number(form.jam_count),
            day_count: Number(form.day_count),
            is_active: form.is_active,
            copy_from_id: form.copy_from_id ? Number(form.copy_from_id) : null,
        };

        if (editing) {
            router.put(`/admin/schedule-sets/${editing.id}`, payload, {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => {
                    setDialogOpen(false);
                    setEditing(null);
                },
            });
        } else {
            router.post('/admin/schedule-sets', payload, {
                onFinish: () => setBusy(false),
                onSuccess: () => setDialogOpen(false),
            });
        }
    }

    function activate(set: ScheduleSet) {
        router.patch(`/admin/schedule-sets/${set.id}/activate`, {}, { preserveScroll: true });
    }

    function destroy(set: ScheduleSet) {
        if (!confirm(`Hapus schedule set "${set.name}"? Semua cell jadwalnya akan ikut terhapus.`)) {
            return;
        }
        router.delete(`/admin/schedule-sets/${set.id}`, { preserveScroll: true });
    }

    function duplicate(set: ScheduleSet) {
        const name = window.prompt('Nama schedule set baru (duplikasi):', `${set.name} (salinan)`);
        if (!name) return;
        router.post(
            '/admin/schedule-sets',
            {
                semester_id: selectedSemesterId,
                name,
                jam_count: set.jam_count,
                day_count: set.day_count,
                is_active: false,
                copy_from_id: set.id,
            },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal (Matrix)" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <FlashMessage />
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Jadwal (Matrix)"
                        description="Kelola versi jadwal bernama per periode. Buka editor untuk mengisi matrix kelas × hari/jam."
                    />
                    <Button onClick={openCreate} disabled={semesters.length === 0}>
                        <Plus className="mr-2 h-4 w-4" />
                        Buat schedule set
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
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Periode</TableHead>
                                <TableHead className="text-center">Hari</TableHead>
                                <TableHead className="text-center">Jam</TableHead>
                                <TableHead className="text-center">Cell</TableHead>
                                <TableHead className="text-center">Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sets.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                                        Belum ada schedule set untuk periode ini.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                sets.map((set) => (
                                    <TableRow key={set.id}>
                                        <TableCell className="font-medium">{set.name}</TableCell>
                                        <TableCell>{set.period?.name ?? '-'}</TableCell>
                                        <TableCell className="text-center">{set.day_count}</TableCell>
                                        <TableCell className="text-center">{set.jam_count}</TableCell>
                                        <TableCell className="text-center">{set.cells_count ?? 0}</TableCell>
                                        <TableCell className="text-center">
                                            {set.is_active ? (
                                                <Badge variant="default" className="gap-1">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    Aktif
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">Draft</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="default"
                                                >
                                                    <Link href={`/admin/schedule-sets/${set.id}/editor`}>
                                                        <Grid3x3 className="mr-1 h-4 w-4" />
                                                        Editor
                                                    </Link>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() => openEdit(set)}
                                                    title="Ubah nama / ukuran"
                                                >
                                                    <Edit3 className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() => duplicate(set)}
                                                    title="Duplikasi"
                                                >
                                                    <Copy className="h-4 w-4" />
                                                </Button>
                                                {!set.is_active && (
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() => activate(set)}
                                                        title="Jadikan aktif"
                                                    >
                                                        <Power className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    onClick={() => destroy(set)}
                                                    title="Hapus"
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

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Ubah schedule set' : 'Buat schedule set baru'}</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-3 py-2">
                            <div className="grid gap-1">
                                <Label>Periode akademik</Label>
                                <Select
                                    value={form.semester_id}
                                    onValueChange={(v) => setForm({ ...form, semester_id: v, copy_from_id: '' })}
                                    disabled={!!editing}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih periode" />
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
                                <Label>Nama</Label>
                                <Input
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    placeholder="Misal: Jadwal Utama Ganjil 2026"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label>Jumlah Hari</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={7}
                                        value={form.day_count}
                                        onChange={(e) => setForm({ ...form, day_count: e.target.value })}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label>Jumlah Jam</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={form.jam_count}
                                        onChange={(e) => setForm({ ...form, jam_count: e.target.value })}
                                    />
                                </div>
                            </div>
                            {!editing && (
                                <>
                                    <div className="grid gap-1">
                                        <Label>Salin dari set lain (opsional)</Label>
                                        <Select
                                            value={form.copy_from_id || 'none'}
                                            onValueChange={(v) =>
                                                setForm({ ...form, copy_from_id: v === 'none' ? '' : v })
                                            }
                                            disabled={!form.semester_id || copyCandidates.length === 0}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Tidak menyalin" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Tidak menyalin</SelectItem>
                                                {copyCandidates.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.is_active}
                                            onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                        />
                                        Tandai sebagai aktif (akan menonaktifkan set lain di periode ini)
                                    </label>
                                </>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Batal
                            </Button>
                            <Button
                                type="button"
                                onClick={submit}
                                disabled={busy || !form.semester_id || !form.name}
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
