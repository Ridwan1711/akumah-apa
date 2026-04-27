import { Head, Link, router } from '@inertiajs/react';
import { BookOpenCheck, Eye, Search } from 'lucide-react';
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
import type { BreadcrumbItem, DiniyahClass, PaginatedData, Student } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tahfidz', href: '/admin/tahfidz' },
];

type Props = {
    students: PaginatedData<Student & { tahfidz_summary?: { total_juz_completed: number; last_hafalan_date: string | null } }>;
    classes: Pick<DiniyahClass, 'id' | 'name' | 'level'>[];
    filters: { class_id?: string; search?: string };
};

export default function TahfidzIndex({ students, classes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/tahfidz', { ...filters, search }, { preserveState: true });
    }

    function handleFilter(key: string, value: string | undefined) {
        router.get('/admin/tahfidz', { ...filters, search, [key]: value === 'all' ? undefined : value }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tahfidz" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Tahfidz Al-Qur'an" description="Pantau progress hafalan santri" />

                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={handleSearch} className="flex items-center gap-2">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input placeholder="Cari santri..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-64 pl-9" />
                        </div>
                        <Button type="submit" variant="outline" size="sm">Cari</Button>
                    </form>
                    <Select value={filters.class_id ?? 'all'} onValueChange={(v) => handleFilter('class_id', v)}>
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
                                <th className="px-4 py-3 text-center font-medium">Juz Selesai</th>
                                <th className="px-4 py-3 text-center font-medium">Progress</th>
                                <th className="px-4 py-3 text-left font-medium">Terakhir Setor</th>
                                <th className="px-4 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.length === 0 ? (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                                    <BookOpenCheck className="mx-auto mb-2 size-8" />Tidak ada data.
                                </td></tr>
                            ) : students.data.map((s) => {
                                const juz = s.tahfidz_summary?.total_juz_completed ?? 0;
                                const pct = Math.round((juz / 30) * 100);
                                return (
                                    <tr key={s.id} className="border-b last:border-0 hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono">{s.nis}</td>
                                        <td className="px-4 py-3 font-medium">{s.full_name}</td>
                                        <td className="px-4 py-3">{s.current_class?.name ?? '-'}</td>
                                        <td className="px-4 py-3 text-center"><Badge variant="outline">{juz} / 30</Badge></td>
                                        <td className="px-4 py-3">
                                            <div className="mx-auto w-24 rounded-full bg-muted h-2">
                                                <div className="rounded-full bg-primary h-2" style={{ width: `${pct}%` }} />
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-muted-foreground">{s.tahfidz_summary?.last_hafalan_date ?? '-'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/tahfidz/${s.id}`}><Eye className="size-4" /></Link>
                                            </Button>
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
