import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { BreadcrumbItem, ViolationType } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pelanggaran', href: '/admin/violations' },
    { title: 'Tipe Pelanggaran', href: '/admin/violations/types' },
];

type Props = { types: ViolationType[] };
const catVariant: Record<string, 'default' | 'secondary' | 'destructive'> = { ringan: 'default', sedang: 'secondary', berat: 'destructive' };

export default function ViolationTypes({ types }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', points: '', category: '' });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/violations/types', { onSuccess: () => { setOpen(false); form.reset(); } });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipe Pelanggaran" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Tipe Pelanggaran" description="Kelola jenis pelanggaran dan poin" />
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild><Button><Plus className="mr-2 size-4" />Tambah</Button></DialogTrigger>
                        <DialogContent>
                            <form onSubmit={handleSubmit}>
                                <DialogHeader><DialogTitle>Tambah Tipe Pelanggaran</DialogTitle></DialogHeader>
                                <div className="grid gap-4 py-4">
                                    <div className="grid gap-2"><Label>Nama</Label><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /><InputError message={form.errors.name} /></div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2"><Label>Poin</Label><Input type="number" min={1} value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} /><InputError message={form.errors.points} /></div>
                                        <div className="grid gap-2"><Label>Kategori</Label>
                                            <Select value={form.data.category} onValueChange={(v) => form.setData('category', v)}>
                                                <SelectTrigger className="w-full"><SelectValue placeholder="Pilih" /></SelectTrigger>
                                                <SelectContent><SelectItem value="ringan">Ringan</SelectItem><SelectItem value="sedang">Sedang</SelectItem><SelectItem value="berat">Berat</SelectItem></SelectContent>
                                            </Select><InputError message={form.errors.category} /></div>
                                    </div>
                                </div>
                                <DialogFooter><Button type="submit" disabled={form.processing}>{form.processing && <Spinner />}Simpan</Button></DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
                <FlashMessage />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50"><tr>
                            <th className="px-4 py-3 text-left font-medium">Nama</th>
                            <th className="px-4 py-3 text-center font-medium">Poin</th>
                            <th className="px-4 py-3 text-center font-medium">Kategori</th>
                            <th className="px-4 py-3 text-right font-medium">Aksi</th>
                        </tr></thead>
                        <tbody>{types.map((t) => (
                            <tr key={t.id} className="border-b last:border-0 hover:bg-muted/30">
                                <td className="px-4 py-3">{t.name}</td>
                                <td className="px-4 py-3 text-center font-bold">{t.points}</td>
                                <td className="px-4 py-3 text-center"><Badge variant={catVariant[t.category] ?? 'outline'}>{t.category}</Badge></td>
                                <td className="px-4 py-3 text-right"><Button size="sm" variant="ghost" onClick={() => { if (confirm('Hapus?')) router.delete(`/admin/violations/types/${t.id}`); }}><Trash2 className="size-4 text-destructive" /></Button></td>
                            </tr>
                        ))}</tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
