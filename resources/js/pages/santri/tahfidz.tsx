import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, TahfidzProgress, TahfidzSummary } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tahfidz', href: '/santri/tahfidz' },
];

type Props = {
    student: { id: number; full_name: string; nis: string };
    summary: TahfidzSummary | null;
    progress: PaginatedData<TahfidzProgress>;
};

export default function SantriTahfidz({ summary, progress }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tahfidz Saya" />
            
            {/* Wrapper utama menggunakan font Inter sebagai default text */}
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8  bg-muted/50">
                
                {/* Header Section */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-4xl font-bold text-foreground  tracking-tight">
                        Tahfidz Saya
                    </h1>
                    <p className="text-muted-foreground">
                        Pantau terus progress dan riwayat hafalan Al-Quran Anda.
                    </p>
                </div>

                {/* Summary Cards */}
                {summary && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {/* Card: Total Juz */}
                        <div className="group flex flex-col justify-center rounded-2xl border border-border bg-white p-6 shadow-sm transition-all hover:shadow-md hover:border-emerald-200">
                            <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground mb-1">
                                Juz Selesai
                            </p>
                            <p className="text-5xl font-bold text-emerald-600  transition-transform group-hover:scale-105 group-hover:origin-left">
                                {summary.total_juz_completed}
                            </p>
                        </div>
                        
                        {/* Card: Terakhir Hafalan */}
                        <div className="group flex flex-col justify-center rounded-2xl border border-border bg-white p-6 shadow-sm transition-all hover:shadow-md hover:border-blue-200">
                            <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground mb-1">
                                Terakhir Hafalan
                            </p>
                            <p className="text-3xl font-bold text-foreground  mt-auto">
                                {summary.last_hafalan_date ?? 'Belum ada data'}
                            </p>
                        </div>
                    </div>
                )}

                {/* Table Section */}
                <div className="flex flex-col gap-4 mt-2">
                    <h2 className="text-2xl font-semibold text-foreground ">
                        Riwayat Setoran
                    </h2>
                    
                    {progress.data.length > 0 ? (
                        <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm text-left whitespace-nowrap">
                                    <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                                        <tr>
                                            <th className="px-6 py-4 font-semibold">Juz</th>
                                            <th className="px-6 py-4 font-semibold">Surah</th>
                                            <th className="px-6 py-4 font-semibold">Ayat</th>
                                            <th className="px-6 py-4 font-semibold">Tipe</th>
                                            <th className="px-6 py-4 font-semibold">Nilai</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/70">
                                        {progress.data.map((p) => (
                                            <tr key={p.id} className="transition-colors hover:bg-muted/50">
                                                <td className="px-6 py-4 font-medium text-foreground">
                                                    {p.juz}
                                                </td>
                                                <td className="px-6 py-4 text-foreground/90">
                                                    {p.surah_to && p.surah_to !== p.surah_from 
                                                        ? `${p.surah_from} - ${p.surah_to}` 
                                                        : p.surah_from}
                                                </td>
                                                <td className="px-6 py-4 text-foreground/90 tabular-nums">
                                                    {p.ayat_from} - {p.ayat_to}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <Badge 
                                                        variant={p.type === 'ziyadah' ? 'default' : 'secondary'}
                                                        className={`px-3 py-1 font-medium capitalize ${
                                                            p.type === 'ziyadah' 
                                                            ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border-emerald-200' 
                                                            : 'bg-amber-100 text-amber-700 hover:bg-amber-200 border-amber-200'
                                                        }`}
                                                    >
                                                        {p.type}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 font-semibold text-foreground">
                                                    {p.grade}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        /* Empty State UX */
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/40 py-12 px-4 text-center">
                            <div className="rounded-full bg-muted p-3 mb-4">
                                <svg className="w-6 h-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <h3 className="text-xl font-semibold text-foreground ">Belum ada riwayat</h3>
                            <p className="text-muted-foreground max-w-sm mt-1">Santri belum memiliki riwayat setoran hafalan saat ini.</p>
                        </div>
                    )}
                    
                    {/* Pagination */}
                    {progress.data.length > 0 && (
                        <div className="mt-4 flex justify-end">
                            <Pagination links={progress.links} from={progress.from} to={progress.to} total={progress.total} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}