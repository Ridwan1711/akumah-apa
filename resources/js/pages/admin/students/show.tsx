import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    Calendar,
    Home,
    IdCard,
    Pencil,
    Plus,
    Shield,
    Trash2,
    User,
    UserCheck,
    UserX,
    Users,
} from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import {
    EmProfileView,
    ProfileHero,
    ProfileInfoRow,
    ProfileSectionCard,
} from '@/components/student-profile';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

type Props = {
    student: Student;
};

export default function StudentShow({ student }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
    ];

    function handleDeleteGuardian(guardianId: number) {
        if (confirm('Hapus data wali ini?')) {
            router.delete(`/admin/students/${student.id}/guardians/${guardianId}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />
            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">

                    <div className="mb-6">
                        <h1 className="text-3xl font-bold text-foreground tracking-tight">
                            Detail Santri
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Informasi lengkap santri beserta data wali dan EMIS.
                        </p>
                    </div>

                    <div className="mb-8">
                        <ProfileHero
                            student={student}
                            editHref={`/admin/students/${student.id}/edit`}
                        />
                    </div>

                    <FlashMessage />

                    <div className="flex flex-col gap-6">
                        {/* Data Pribadi */}
                        <ProfileSectionCard icon={User} title="Data Pribadi" subtitle="Informasi dasar santri">
                            <ProfileInfoRow icon={BookOpen} label="NIS" value={<span className="font-mono tracking-wider">{student.nis}</span>} />
                            <ProfileInfoRow icon={IdCard} label="NIK" value={student.nik} mono />
                            <ProfileInfoRow icon={User} label="Nama Lengkap" value={student.full_name} />
                            <ProfileInfoRow icon={Home} label="Tempat Lahir" value={student.birth_place} />
                            <ProfileInfoRow
                                icon={Calendar}
                                label="Tanggal Lahir"
                                value={
                                    student.birth_date
                                        ? new Date(student.birth_date).toLocaleDateString('id-ID', {
                                              day: 'numeric',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : null
                                }
                            />
                            <ProfileInfoRow
                                icon={Users}
                                label="Jenis Kelamin"
                                value={student.gender === 'L' ? 'Laki-laki' : 'Perempuan'}
                            />
                            <ProfileInfoRow icon={Home} label="Alamat" value={student.address} />
                            <ProfileInfoRow
                                icon={BookOpen}
                                label="Tahun Masuk"
                                value={String(student.admission_year)}
                            />
                            <ProfileInfoRow
                                icon={BookOpen}
                                label="Kelas"
                                value={student.current_class?.name ?? 'Belum ditempatkan'}
                            />
                            <ProfileInfoRow
                                label="Status"
                                value={
                                    <Badge
                                        variant={
                                            student.status === 'active' ? 'default' :
                                            student.status === 'wafat' ? 'destructive' : 'outline'
                                        }
                                    >
                                        {student.status === 'active' ? 'Aktif' :
                                         student.status === 'alumni' ? 'Alumni' :
                                         student.status === 'keluar' ? 'Keluar' : 'Wafat'}
                                    </Badge>
                                }
                            />
                        </ProfileSectionCard>

                        {/* Status Akun */}
                        <ProfileSectionCard icon={UserCheck} title="Status Akun" subtitle="Informasi akun pengguna">
                            {student.user ? (
                                <>
                                    <ProfileInfoRow
                                        icon={UserCheck}
                                        label="Status Akun"
                                        value={
                                            <span className="flex items-center gap-2">
                                                <UserCheck className="size-4 text-green-600" />
                                                <span className="font-medium text-green-700">Akun Aktif</span>
                                            </span>
                                        }
                                    />
                                    <ProfileInfoRow icon={User} label="Username" value={<span className="font-mono">{student.user.username}</span>} />
                                    <ProfileInfoRow icon={User} label="Email" value={student.user.email} />
                                </>
                            ) : (
                                <div className="flex items-center gap-3 py-6">
                                    <UserX className="size-5 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        Belum memiliki akun. Gunakan menu <strong>Generate Akun</strong>.
                                    </span>
                                </div>
                            )}
                        </ProfileSectionCard>

                        {/* Data EMIS */}
                        <EmProfileView
                            emProfile={student.emis_profile}
                            showCatatan
                            compact={false}
                        />

                        {/* Wali Santri */}
                        <ProfileSectionCard
                            icon={Shield}
                            title="Data Wali Santri"
                            subtitle="Orang tua atau wali yang bertanggung jawab"
                            action={
                                <div className="flex gap-2">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link href={`/admin/students/${student.id}/guardians/attach`}>
                                            Hubungkan Wali
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link href={`/admin/students/${student.id}/guardians/create`}>
                                            <Plus className="mr-2 size-4" />
                                            Tambah Wali Baru
                                        </Link>
                                    </Button>
                                </div>
                            }
                        >
                            {!student.guardians?.length ? (
                                <div className="py-8 text-center">
                                    <p className="text-sm text-muted-foreground">Belum ada data wali.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto py-2">
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
                        </ProfileSectionCard>
                    </div>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}
