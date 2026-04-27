import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, Student, TahfidzProgress, TahfidzTarget } from '@/types';

type Props = {
    student: Student;
    targets: TahfidzTarget[];
    progress: PaginatedData<TahfidzProgress>;
};

const gradeLabels: Record<string, string> = {
    mumtaz: 'Mumtaz', jayyid_jiddan: 'Jayyid Jiddan', jayyid: 'Jayyid', maqbul: 'Maqbul', rasib: 'Rasib',
};
const statusLabels: Record<TahfidzTarget['status'], string> = {
    ongoing: 'Berjalan',
    completed: 'Selesai',
    overdue: 'Terlambat',
};

function formatDateTime(value?: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function TahfidzShow({ student, targets, progress }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Tahfidz', href: '/admin/tahfidz' },
        { title: student.full_name, href: `/admin/tahfidz/${student.id}` },
    ];

    const [targetDialog, setTargetDialog] = useState(false);
    const [progressDialog, setProgressDialog] = useState(false);
    const [targetEditDialog, setTargetEditDialog] = useState(false);
    const [progressEditDialog, setProgressEditDialog] = useState(false);
    const [editingTarget, setEditingTarget] = useState<TahfidzTarget | null>(null);
    const [editingProgress, setEditingProgress] = useState<TahfidzProgress | null>(null);

    const targetForm = useForm({ student_id: String(student.id), target_juz: '', start_date: '', end_date: '' });
    const progressForm = useForm({ student_id: String(student.id), juz: '', surah_from: '', surah_to: '', ayat_from: '', ayat_to: '', type: '', grade: '', notes: '' });
    const targetEditForm = useForm({ target_juz: '', start_date: '', end_date: '', status: 'ongoing' });
    const progressEditForm = useForm({ juz: '', surah_from: '', surah_to: '', ayat_from: '', ayat_to: '', type: '', grade: '', notes: '' });

    function submitTarget(e: React.FormEvent) {
        e.preventDefault();
        targetForm.post('/admin/tahfidz/targets', { onSuccess: () => { setTargetDialog(false); targetForm.reset(); } });
    }

    function submitProgress(e: React.FormEvent) {
        e.preventDefault();
        progressForm.post('/admin/tahfidz/progress', { onSuccess: () => { setProgressDialog(false); progressForm.reset(); } });
    }

    function openTargetEdit(target: TahfidzTarget) {
        setEditingTarget(target);
        targetEditForm.setData({
            target_juz: String(target.target_juz),
            start_date: target.start_date,
            end_date: target.end_date,
            status: target.status,
        });
        setTargetEditDialog(true);
    }

    function submitTargetEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editingTarget) return;
        targetEditForm.put(`/admin/tahfidz/targets/${editingTarget.id}`, {
            onSuccess: () => {
                setTargetEditDialog(false);
                setEditingTarget(null);
            },
        });
    }

    function deleteTarget(target: TahfidzTarget) {
        if (!confirm('Hapus target tahfidz ini?')) return;
        router.delete(`/admin/tahfidz/targets/${target.id}`);
    }

    function openProgressEdit(item: TahfidzProgress) {
        setEditingProgress(item);
        progressEditForm.setData({
            juz: String(item.juz),
            surah_from: item.surah_from,
            surah_to: item.surah_to ?? '',
            ayat_from: String(item.ayat_from),
            ayat_to: String(item.ayat_to),
            type: item.type,
            grade: item.grade,
            notes: item.notes ?? '',
        });
        setProgressEditDialog(true);
    }

    function submitProgressEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editingProgress) return;
        progressEditForm.put(`/admin/tahfidz/progress/${editingProgress.id}`, {
            onSuccess: () => {
                setProgressEditDialog(false);
                setEditingProgress(null);
            },
        });
    }

    function deleteProgress(item: TahfidzProgress) {
        if (!confirm('Hapus setoran tahfidz ini?')) return;
        router.delete(`/admin/tahfidz/progress/${item.id}`);
    }

    const summary = student.tahfidz_summary;
    const activeTargetsCount = targets.filter((t) => t.status === 'ongoing').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tahfidz - ${student.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="rounded-xl border bg-card p-4 md:p-5">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <Heading title={student.full_name} description={`NIS: ${student.nis} · Kelas: ${student.current_class?.name ?? '-'}`} />
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline" className="h-8 px-3">{summary?.total_juz_completed ?? 0}/30 Juz</Badge>
                            <Badge variant="secondary" className="h-8 px-3">{activeTargetsCount} Target Aktif</Badge>
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Dialog open={targetDialog} onOpenChange={setTargetDialog}>
                            <DialogTrigger asChild><Button variant="outline" size="sm"><Plus className="mr-1 size-3" />Set Target</Button></DialogTrigger>
                            <DialogContent className="sm:max-w-lg">
                                <form onSubmit={submitTarget}>
                                    <DialogHeader><DialogTitle>Set Target Tahfidz</DialogTitle></DialogHeader>
                                    <div className="grid gap-4 py-4">
                                        <div className="grid gap-2"><Label>Target Juz</Label><Input type="number" min={1} max={30} value={targetForm.data.target_juz} onChange={(e) => targetForm.setData('target_juz', e.target.value)} /><InputError message={targetForm.errors.target_juz} /></div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2"><Label>Mulai</Label><Input type="date" value={targetForm.data.start_date} onChange={(e) => targetForm.setData('start_date', e.target.value)} /><InputError message={targetForm.errors.start_date} /></div>
                                            <div className="grid gap-2"><Label>Selesai</Label><Input type="date" value={targetForm.data.end_date} onChange={(e) => targetForm.setData('end_date', e.target.value)} /><InputError message={targetForm.errors.end_date} /></div>
                                        </div>
                                    </div>
                                    <DialogFooter><Button type="submit" disabled={targetForm.processing}>{targetForm.processing && <Spinner />}Simpan</Button></DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Dialog open={progressDialog} onOpenChange={setProgressDialog}>
                            <DialogTrigger asChild><Button size="sm"><Plus className="mr-1 size-3" />Input Setoran</Button></DialogTrigger>
                            <DialogContent className="sm:max-w-2xl">
                                <form onSubmit={submitProgress}>
                                    <DialogHeader><DialogTitle>Input Setoran Hafalan</DialogTitle></DialogHeader>
                                    <div className="grid gap-4 py-4">
                                        <div className="grid grid-cols-3 gap-4">
                                            <div className="grid gap-2"><Label>Juz</Label><Input type="number" min={1} max={30} value={progressForm.data.juz} onChange={(e) => progressForm.setData('juz', e.target.value)} /><InputError message={progressForm.errors.juz} /></div>
                                            <div className="grid gap-2"><Label>Surah Dari</Label><Input value={progressForm.data.surah_from} onChange={(e) => progressForm.setData('surah_from', e.target.value)} placeholder="Al-Baqarah" /><InputError message={progressForm.errors.surah_from} /></div>
                                            <div className="grid gap-2"><Label>Surah Sampai</Label><Input value={progressForm.data.surah_to} onChange={(e) => progressForm.setData('surah_to', e.target.value)} placeholder="Kosongkan jika sama" /><InputError message={progressForm.errors.surah_to} /></div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2"><Label>Ayat Dari</Label><Input type="number" min={1} value={progressForm.data.ayat_from} onChange={(e) => progressForm.setData('ayat_from', e.target.value)} /><InputError message={progressForm.errors.ayat_from} /></div>
                                            <div className="grid gap-2"><Label>Ayat Sampai</Label><Input type="number" min={1} value={progressForm.data.ayat_to} onChange={(e) => progressForm.setData('ayat_to', e.target.value)} /><InputError message={progressForm.errors.ayat_to} /></div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2"><Label>Tipe</Label>
                                                <Select value={progressForm.data.type} onValueChange={(v) => progressForm.setData('type', v)}>
                                                    <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                                    <SelectContent><SelectItem value="ziyadah">Ziyadah</SelectItem><SelectItem value="murojaah">Murojaah</SelectItem></SelectContent>
                                                </Select><InputError message={progressForm.errors.type} /></div>
                                            <div className="grid gap-2"><Label>Nilai</Label>
                                                <Select value={progressForm.data.grade} onValueChange={(v) => progressForm.setData('grade', v)}>
                                                    <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="mumtaz">Mumtaz</SelectItem><SelectItem value="jayyid_jiddan">Jayyid Jiddan</SelectItem>
                                                        <SelectItem value="jayyid">Jayyid</SelectItem><SelectItem value="maqbul">Maqbul</SelectItem><SelectItem value="rasib">Rasib</SelectItem>
                                                    </SelectContent>
                                                </Select><InputError message={progressForm.errors.grade} /></div>
                                        </div>
                                        <div className="grid gap-2"><Label>Catatan</Label><Input value={progressForm.data.notes} onChange={(e) => progressForm.setData('notes', e.target.value)} /></div>
                                    </div>
                                    <DialogFooter><Button type="submit" disabled={progressForm.processing}>{progressForm.processing && <Spinner />}Simpan</Button></DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
                <FlashMessage />

                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="shadow-sm">
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Juz Selesai</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold">{summary?.total_juz_completed ?? 0}<span className="text-lg text-muted-foreground"> / 30</span></p></CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Target Aktif</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold">{activeTargetsCount}</p></CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Terakhir Setor</CardTitle></CardHeader>
                        <CardContent><p className="text-lg font-medium">{summary?.last_hafalan_date ?? '-'}</p></CardContent>
                    </Card>
                </div>

                {targets.length > 0 && (
                    <Card className="shadow-sm">
                        <CardHeader><CardTitle>Target Tahfidz</CardTitle></CardHeader>
                        <CardContent>
                            <div className="space-y-3">{targets.map((t) => (
                                <div key={t.id} className="rounded-lg border p-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-base font-semibold">{t.target_juz} Juz</span>
                                                <Badge variant={t.status === 'completed' ? 'default' : t.status === 'overdue' ? 'destructive' : 'secondary'}>
                                                    {statusLabels[t.status]}
                                                </Badge>
                                            </div>
                                            <div className="mt-1 text-sm text-muted-foreground">{t.start_date} - {t.end_date}</div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button type="button" variant="ghost" size="sm" onClick={() => openTargetEdit(t)}>
                                                <Pencil className="size-3" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="sm" onClick={() => deleteTarget(t)}>
                                                <Trash2 className="size-3 text-destructive" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-3 rounded-md bg-muted/50 px-2.5 py-2 text-xs text-muted-foreground">
                                        Dibuat: {formatDateTime(t.created_at)} · Terakhir diubah: {formatDateTime(t.updated_at)}
                                    </div>
                                </div>
                            ))}</div>
                        </CardContent>
                    </Card>
                )}

                <Card className="shadow-sm">
                    <CardHeader><CardTitle>Riwayat Setoran</CardTitle></CardHeader>
                    <CardContent>
                        {progress.data.length === 0 ? <p className="py-4 text-sm text-muted-foreground">Belum ada setoran.</p> : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50"><tr>
                                        <th className="px-3 py-2 text-left font-medium">Tanggal</th>
                                        <th className="px-3 py-2 text-left font-medium">Juz</th>
                                        <th className="px-3 py-2 text-left font-medium">Surah</th>
                                        <th className="px-3 py-2 text-left font-medium">Ayat</th>
                                        <th className="px-3 py-2 text-left font-medium">Tipe</th>
                                        <th className="px-3 py-2 text-left font-medium">Nilai</th>
                                        <th className="px-3 py-2 text-left font-medium">Penguji</th>
                                        <th className="px-3 py-2 text-left font-medium">Audit Trail</th>
                                        <th className="px-3 py-2 text-left font-medium">Aksi</th>
                                    </tr></thead>
                                    <tbody>{progress.data.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0">
                                            <td className="px-3 py-2 text-xs">{p.validated_at ? new Date(p.validated_at).toLocaleDateString('id-ID') : '-'}</td>
                                            <td className="px-3 py-2">{p.juz}</td>
                                            <td className="px-3 py-2">{p.surah_to && p.surah_to !== p.surah_from ? `${p.surah_from} - ${p.surah_to}` : p.surah_from}</td>
                                            <td className="px-3 py-2">{p.ayat_from}-{p.ayat_to}</td>
                                            <td className="px-3 py-2"><Badge variant={p.type === 'ziyadah' ? 'default' : 'secondary'}>{p.type}</Badge></td>
                                            <td className="px-3 py-2">{gradeLabels[p.grade] ?? p.grade}</td>
                                            <td className="px-3 py-2">{p.validator?.name ?? '-'}</td>
                                            <td className="px-3 py-2 text-xs text-muted-foreground">
                                                <div className="space-y-1 rounded-md bg-muted/50 px-2 py-1.5">
                                                    <div>Dibuat: {formatDateTime(p.created_at)}</div>
                                                    <div>Diubah: {formatDateTime(p.updated_at)}</div>
                                                    <div>Validasi: {formatDateTime(p.validated_at)}</div>
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center gap-1">
                                                    <Button type="button" size="sm" variant="ghost" onClick={() => openProgressEdit(p)}>
                                                        <Pencil className="size-3" />
                                                    </Button>
                                                    <Button type="button" size="sm" variant="ghost" onClick={() => deleteProgress(p)}>
                                                        <Trash2 className="size-3 text-destructive" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}</tbody>
                                </table>
                            </div>
                        )}
                        <div className="mt-3"><Pagination links={progress.links} from={progress.from} to={progress.to} total={progress.total} /></div>
                    </CardContent>
                </Card>

                <Dialog open={targetEditDialog} onOpenChange={setTargetEditDialog}>
                    <DialogContent className="sm:max-w-lg">
                        <form onSubmit={submitTargetEdit}>
                            <DialogHeader><DialogTitle>Edit Target Tahfidz</DialogTitle></DialogHeader>
                            <div className="grid gap-4 py-4">
                                <div className="grid gap-2"><Label>Target Juz</Label><Input type="number" min={1} max={30} value={targetEditForm.data.target_juz} onChange={(e) => targetEditForm.setData('target_juz', e.target.value)} /><InputError message={targetEditForm.errors.target_juz} /></div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2"><Label>Mulai</Label><Input type="date" value={targetEditForm.data.start_date} onChange={(e) => targetEditForm.setData('start_date', e.target.value)} /><InputError message={targetEditForm.errors.start_date} /></div>
                                    <div className="grid gap-2"><Label>Selesai</Label><Input type="date" value={targetEditForm.data.end_date} onChange={(e) => targetEditForm.setData('end_date', e.target.value)} /><InputError message={targetEditForm.errors.end_date} /></div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Status</Label>
                                    <Select value={targetEditForm.data.status} onValueChange={(v) => targetEditForm.setData('status', v)}>
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Pilih status" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ongoing">Berjalan</SelectItem>
                                            <SelectItem value="completed">Selesai</SelectItem>
                                            <SelectItem value="overdue">Terlambat</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={targetEditForm.errors.status} />
                                </div>
                            </div>
                            <DialogFooter><Button type="submit" disabled={targetEditForm.processing}>{targetEditForm.processing && <Spinner />}Simpan Perubahan</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog open={progressEditDialog} onOpenChange={setProgressEditDialog}>
                    <DialogContent className="sm:max-w-2xl">
                        <form onSubmit={submitProgressEdit}>
                            <DialogHeader><DialogTitle>Edit Setoran Hafalan</DialogTitle></DialogHeader>
                            <div className="grid gap-4 py-4">
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="grid gap-2"><Label>Juz</Label><Input type="number" min={1} max={30} value={progressEditForm.data.juz} onChange={(e) => progressEditForm.setData('juz', e.target.value)} /><InputError message={progressEditForm.errors.juz} /></div>
                                    <div className="grid gap-2"><Label>Surah Dari</Label><Input value={progressEditForm.data.surah_from} onChange={(e) => progressEditForm.setData('surah_from', e.target.value)} /><InputError message={progressEditForm.errors.surah_from} /></div>
                                    <div className="grid gap-2"><Label>Surah Sampai</Label><Input value={progressEditForm.data.surah_to} onChange={(e) => progressEditForm.setData('surah_to', e.target.value)} /><InputError message={progressEditForm.errors.surah_to} /></div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2"><Label>Ayat Dari</Label><Input type="number" min={1} value={progressEditForm.data.ayat_from} onChange={(e) => progressEditForm.setData('ayat_from', e.target.value)} /><InputError message={progressEditForm.errors.ayat_from} /></div>
                                    <div className="grid gap-2"><Label>Ayat Sampai</Label><Input type="number" min={1} value={progressEditForm.data.ayat_to} onChange={(e) => progressEditForm.setData('ayat_to', e.target.value)} /><InputError message={progressEditForm.errors.ayat_to} /></div>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2"><Label>Tipe</Label>
                                        <Select value={progressEditForm.data.type} onValueChange={(v) => progressEditForm.setData('type', v)}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                            <SelectContent><SelectItem value="ziyadah">Ziyadah</SelectItem><SelectItem value="murojaah">Murojaah</SelectItem></SelectContent>
                                        </Select><InputError message={progressEditForm.errors.type} />
                                    </div>
                                    <div className="grid gap-2"><Label>Nilai</Label>
                                        <Select value={progressEditForm.data.grade} onValueChange={(v) => progressEditForm.setData('grade', v)}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="mumtaz">Mumtaz</SelectItem><SelectItem value="jayyid_jiddan">Jayyid Jiddan</SelectItem>
                                                <SelectItem value="jayyid">Jayyid</SelectItem><SelectItem value="maqbul">Maqbul</SelectItem><SelectItem value="rasib">Rasib</SelectItem>
                                            </SelectContent>
                                        </Select><InputError message={progressEditForm.errors.grade} />
                                    </div>
                                </div>
                                <div className="grid gap-2"><Label>Catatan</Label><Input value={progressEditForm.data.notes} onChange={(e) => progressEditForm.setData('notes', e.target.value)} /></div>
                            </div>
                            <DialogFooter><Button type="submit" disabled={progressEditForm.processing}>{progressEditForm.processing && <Spinner />}Simpan Perubahan</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
