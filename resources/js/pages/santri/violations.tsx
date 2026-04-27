import { Head } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, ShieldCheck } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, StudentViolation, ViolationSummary } from '@/types';

/** Fonts & theme: see resources/css/app.css (Plus Jakarta Sans, Manhood tokens). */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pelanggaran', href: '/santri/violations' },
];

type Props = {
    student: { id: number; full_name: string; nis: string };
    summary: ViolationSummary | null;
    violations: PaginatedData<StudentViolation>;
};

export default function SantriViolations({ summary, violations }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pelanggaran Saya" />
            
            <div className="min-h-full bg-muted/50 ">
                <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                    
                    {/* Header Section */}
                    <div className="mb-6 flex flex-col gap-1">
                        <h1 className="text-3xl font-bold text-foreground  tracking-tight">
                            Catatan Kedisiplinan
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Pantau riwayat pelanggaran dan akumulasi poin kedisiplinan Anda.
                        </p>
                    </div>

                    {/* Summary Cards */}
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {/* Card: Total Poin */}
                        <div className="group relative overflow-hidden rounded-2xl border border-rose-100 bg-white p-6 shadow-sm transition-all hover:shadow-md hover:border-rose-200">
                            <div className="absolute -right-6 -top-6 text-rose-50 opacity-50 transition-transform group-hover:scale-110">
                                <AlertTriangle size={120} strokeWidth={1} />
                            </div>
                            <div className="relative z-10">
                                <p className=" text-[11px] font-bold uppercase tracking-widest text-muted-foreground mb-1">
                                    Total Poin Pelanggaran
                                </p>
                                <div className="flex items-baseline gap-2">
                                    <p className=" text-5xl font-bold text-rose-600">
                                        {summary?.total_points ?? 0}
                                    </p>
                                    <p className="text-sm font-medium text-rose-400">poin</p>
                                </div>
                            </div>
                        </div>

                        {/* Card: Pelanggaran Terakhir */}
                        <div className="group relative overflow-hidden rounded-2xl border border-border bg-white p-6 shadow-sm transition-all hover:shadow-md hover:border-border">
                            <div className="absolute -right-4 -top-4 text-muted/25 transition-transform group-hover:scale-110">
                                <CalendarClock size={110} strokeWidth={1} />
                            </div>
                            <div className="relative z-10 flex h-full flex-col justify-between">
                                <p className=" text-[11px] font-bold uppercase tracking-widest text-muted-foreground mb-1">
                                    Pelanggaran Terakhir
                                </p>
                                <p className=" text-3xl font-bold text-foreground mt-auto">
                                    {summary?.last_violation_date ?? <span className="text-muted-foreground italic text-2xl">Tidak ada</span>}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Content Section */}
                    <div className="flex flex-col gap-4">
                        <h2 className="text-2xl font-bold text-foreground  mb-2">
                            Riwayat Pelanggaran
                        </h2>

                        {violations.data.length > 0 ? (
                            <div className="overflow-hidden rounded-2xl border border-border bg-white shadow-sm">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm whitespace-nowrap">
                                        <thead className="border-b border-border/70 bg-muted/40 text-muted-foreground">
                                            <tr>
                                                <th className="px-6 py-4 font-semibold ">Tanggal</th>
                                                <th className="px-6 py-4 font-semibold ">Jenis Pelanggaran</th>
                                                <th className="px-6 py-4 font-semibold ">Kategori</th>
                                                <th className="px-6 py-4 text-center font-semibold ">Poin</th>
                                                <th className="px-6 py-4 text-center font-semibold ">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border/60">
                                            {violations.data.map((v) => (
                                                <tr key={v.id} className="transition-colors hover:bg-muted/50">
                                                    <td className="px-6 py-4 text-muted-foreground font-medium tabular-nums">
                                                        {v.date}
                                                    </td>
                                                    <td className="px-6 py-4 text-foreground font-medium">
                                                        {v.violation_type?.name}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <Badge 
                                                            variant="outline" 
                                                            className={`px-2.5 py-0.5 border-none font-semibold capitalize ${
                                                                v.violation_type?.category === 'berat' 
                                                                    ? 'bg-rose-100 text-rose-700' 
                                                                    : v.violation_type?.category === 'sedang'
                                                                    ? 'bg-amber-100 text-amber-700'
                                                                    : 'bg-muted text-foreground/90'
                                                            }`}
                                                        >
                                                            {v.violation_type?.category}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-50 text-rose-600 font-bold ">
                                                            {v.violation_type?.points}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-center">
                                                        <Badge 
                                                            className={`px-3 py-1 font-medium capitalize border-none shadow-none ${
                                                                v.status === 'resolved' 
                                                                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' 
                                                                    : 'bg-sidebar text-white hover:bg-sidebar/80'
                                                            }`}
                                                        >
                                                            {v.status === 'resolved' ? 'Selesai' : 'Aktif'}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ) : (
                            /* Positive Empty State */
                            <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/50 py-16 px-4 text-center">
                                <div className="rounded-full bg-emerald-100 p-4 mb-4 text-emerald-600 shadow-sm border border-emerald-200">
                                    <ShieldCheck size={36} strokeWidth={1.5} />
                                </div>
                                <h3 className="text-2xl font-bold text-foreground ">
                                    Alhamdulillah, Bersih!
                                </h3>
                                <p className=" text-sm text-muted-foreground max-w-md mt-2 leading-relaxed">
                                    Anda tidak memiliki riwayat pelanggaran. Pertahankan terus kedisiplinan dan akhlakul karimah Anda selama di pesantren.
                                </p>
                            </div>
                        )}
                        
                        {/* Pagination */}
                        {violations.data.length > 0 && (
                            <div className="mt-4 flex justify-end">
                                <Pagination links={violations.links} from={violations.from} to={violations.to} total={violations.total} />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}