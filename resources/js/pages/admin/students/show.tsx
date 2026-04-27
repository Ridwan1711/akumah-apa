import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UserCheck, UserX } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

type Props = {
    student: Student;
};

const statusMap: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    active: { label: 'Aktif', variant: 'default' },
    alumni: { label: 'Alumni', variant: 'secondary' },
    keluar: { label: 'Keluar', variant: 'outline' },
    wafat: { label: 'Wafat', variant: 'destructive' },
};

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-3 gap-2 py-2 border-b last:border-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="col-span-2 text-sm">{value || '-'}</dd>
        </div>
    );
}

export default function StudentShow({ student }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
    ];

    const status = statusMap[student.status];

    function handleDeleteGuardian(guardianId: number) {
        if (confirm('Hapus data wali ini?')) {
            router.delete(`/admin/students/${student.id}/guardians/${guardianId}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={student.full_name} description={`NIS: ${student.nis}`} />
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/students/${student.id}/edit`}>
                                <Pencil className="mr-2 size-4" />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <FlashMessage />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Pribadi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl>
                                <DetailRow label="NIS" value={<span className="font-mono">{student.nis}</span>} />
                                <DetailRow label="NIK" value={student.nik} />
                                <DetailRow label="Nama Lengkap" value={student.full_name} />
                                <DetailRow label="Tempat, Tgl Lahir" value={
                                    student.birth_place || student.birth_date
                                        ? `${student.birth_place ?? ''}, ${student.birth_date ?? ''}`
                                        : null
                                } />
                                <DetailRow label="Jenis Kelamin" value={student.gender === 'L' ? 'Laki-laki' : 'Perempuan'} />
                                <DetailRow label="Alamat" value={student.address} />
                                <DetailRow label="Tahun Masuk" value={student.admission_year} />
                                <DetailRow label="Status" value={
                                    <Badge variant={status?.variant ?? 'outline'}>{status?.label ?? student.status}</Badge>
                                } />
                                <DetailRow label="Kelas" value={student.current_class?.name ?? 'Belum ditempatkan'} />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Status Akun</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {student.user ? (
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <UserCheck className="size-5 text-green-600" />
                                        <span className="text-sm font-medium">Akun Aktif</span>
                                    </div>
                                    <dl>
                                        <DetailRow label="Username" value={<span className="font-mono">{student.user.username}</span>} />
                                        <DetailRow label="Email" value={student.user.email} />
                                    </dl>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 py-4">
                                    <UserX className="size-5 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        Belum memiliki akun. Gunakan menu Generate Akun.
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Data Wali Santri</CardTitle>
                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={`/admin/students/${student.id}/guardians/attach`}>
                                        Tambah Wali yang Ada
                                    </Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={`/admin/students/${student.id}/guardians/create`}>
                                        <Plus className="mr-2 size-4" />
                                        Tambah Wali Baru
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!student.guardians?.length ? (
                            <p className="text-sm text-muted-foreground py-4">Belum ada data wali.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50">
                                        <tr>
                                            <th className="px-4 py-2 text-left font-medium">Nama</th>
                                            <th className="px-4 py-2 text-left font-medium">Hubungan</th>
                                            <th className="px-4 py-2 text-left font-medium">Telepon</th>
                                            <th className="px-4 py-2 text-left font-medium">Akun</th>
                                            <th className="px-4 py-2 text-right font-medium">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {student.guardians.map((g) => (
                                            <tr key={g.id} className="border-b last:border-0">
                                                <td className="px-4 py-2 font-medium">{g.full_name}</td>
                                                <td className="px-4 py-2 capitalize">{g.pivot?.relationship ?? g.relationship ?? '-'}</td>
                                                <td className="px-4 py-2">{g.phone ?? '-'}</td>
                                                <td className="px-4 py-2">
                                                    <Badge variant={g.user_id ? 'default' : 'outline'}>
                                                        {g.user_id ? 'Aktif' : 'Belum'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={`/admin/students/${student.id}/guardians/${g.id}/edit`}>
                                                                <Pencil className="size-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button variant="ghost" size="sm" onClick={() => handleDeleteGuardian(g.id)}>
                                                            <Trash2 className="size-4 text-destructive" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
