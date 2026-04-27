import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Data Anak', href: '/wali/children' },
];

type Props = { children: Student[] };

export default function WaliChildren({ children }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Anak" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Data Anak" description="Lihat informasi akademik dan aktivitas anak Anda" />

                {children.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <Users className="mx-auto mb-2 size-8" />
                        Data anak belum terhubung.
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {children.map((child) => (
                            <div key={child.id} className="rounded-xl border p-6">
                                <h3 className="text-lg font-semibold">{child.full_name}</h3>
                                <p className="mb-4 text-sm text-muted-foreground">NIS: {child.nis} | Kelas: {child.current_class?.name ?? '-'}</p>
                                <div className="mb-4 grid grid-cols-2 gap-3">
                                    <div className="rounded-lg bg-muted/30 p-3 text-center">
                                        <p className="text-2xl font-bold text-green-600">{child.tahfidz_summary?.total_juz_completed ?? 0}</p>
                                        <p className="text-xs text-muted-foreground">Juz Hafalan</p>
                                    </div>
                                    <div className="rounded-lg bg-muted/30 p-3 text-center">
                                        <p className="text-2xl font-bold text-red-600">{child.violation_summary?.total_points ?? 0}</p>
                                        <p className="text-xs text-muted-foreground">Poin Pelanggaran</p>
                                    </div>
                                </div>
                                <div className="flex items-center justify-between">
                                    <Badge variant={child.status === 'active' ? 'default' : 'secondary'}>{child.status}</Badge>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/wali/children/${child.id}`}>Lihat Detail <ArrowRight className="ml-1 size-3" /></Link>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
