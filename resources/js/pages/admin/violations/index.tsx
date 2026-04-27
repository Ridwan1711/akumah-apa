import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DiniyahClass, PaginatedData, Student, StudentViolation } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pelanggaran', href: '/admin/violations' },
];

type Props = {
    students: PaginatedData<Student & { violation_summary?: { total_points: number } }>;
    classes: Pick<DiniyahClass, 'id' | 'name'>[];
    recentViolations: (StudentViolation & { student?: Pick<Student, 'id' | 'full_name' | 'nis'>; violation_type?: { id: number; name: string; points: number } })[];
    filters: { class_id?: string; search?: string };
};

export default function ViolationIndex({ students, classes, recentViolations, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/violations', { ...filters, search }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pelanggaran" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Pelanggaran & Poin" description="Catat dan pantau pelanggaran santri" />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href="/admin/violations/types">Tipe Pelanggaran</Link></Button>
                        <Button asChild><Link href="/admin/violations/create"><Plus className="mr-2 size-4" />Catat Pelanggaran</Link></Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={handleSearch} className="flex items-center gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input placeholder="Cari santri..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-64 pl-9" />
                        </div>
                        <Button type="submit" variant="outline" size="sm">Cari</Button>
                    </form>
                    <Select value={filters.class_id ?? 'all'} onValueChange={(v) => router.get('/admin/violations', { ...filters, search, class_id: v === 'all' ? undefined : v }, { preserveState: true })}>
                        <SelectTrigger className="w-48"><SelectValue placeholder="Kelas" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua Kelas</SelectItem>
                            {classes.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">NIS</th>
                                <th className="px-4 py-3 text-left font-medium">Nama</th>
                                <th className="px-4 py-3 text-left font-medium">Kelas</th>
                                <th className="px-4 py-3 text-center font-medium">Total Poin</th>
                                <th className="px-4 py-3 text-center font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.length === 0 ? (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">Tidak ada data.</td></tr>
                            ) : students.data.map((s) => {
                                const pts = s.violation_summary?.total_points ?? 0;
                                return (
                                    <tr key={s.id} className="border-b last:border-0 hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono">{s.nis}</td>
                                        <td className="px-4 py-3 font-medium">{s.full_name}</td>
                                        <td className="px-4 py-3">{s.current_class?.name ?? '-'}</td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge variant={pts >= 50 ? 'destructive' : pts >= 20 ? 'secondary' : 'outline'}>{pts}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {pts >= 75 && <Badge variant="destructive"><AlertTriangle className="mr-1 size-3" />Kritis</Badge>}
                                            {pts >= 50 && pts < 75 && <Badge variant="secondary">Peringatan</Badge>}
                                            {pts < 50 && <Badge variant="outline">Normal</Badge>}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <Pagination links={students.links} from={students.from} to={students.to} total={students.total} />
            </div>
        </AppLayout>
    );
}
