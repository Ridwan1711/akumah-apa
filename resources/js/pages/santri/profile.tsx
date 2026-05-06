import { Head, Link } from '@inertiajs/react';
import { BookOpen, Calendar, Home, IdCard, Phone, Shield, User, Users } from 'lucide-react';
import {
    EmProfileView,
    ProfileHero,
    ProfileInfoRow,
    ProfileSectionCard,
} from '@/components/student-profile';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Profil', href: '/santri/profile' },
];

type Props = { student: Student };

function GuardianCard({
    guardian,
    index,
}: {
    guardian: NonNullable<Student['guardians']>[number];
    index: number;
}) {
    const initials = guardian.full_name
        ? guardian.full_name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
        : '?';

    const relationship = guardian.relationship ?? guardian.pivot?.relationship;

    return (
        <div className="py-5 last:pb-5">
            <div className="mb-4 flex items-center gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sidebar text-lg font-bold text-white shadow-sm">
                    {initials}
                </div>
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <p className="text-xl font-bold leading-tight text-foreground">{guardian.full_name}</p>
                        <Badge variant="outline" className="bg-muted/40 text-xs font-medium text-muted-foreground border-border">
                            Wali {index}
                        </Badge>
                    </div>
                    {relationship && (
                        <p className="mt-0.5 text-sm capitalize text-emerald-600 font-medium">{relationship}</p>
                    )}
                </div>
            </div>

            <div className="grid gap-0 rounded-xl border border-border bg-white overflow-hidden sm:grid-cols-2">
                <div className="flex items-center gap-3 p-4 border-b border-border/70 sm:border-b-0 sm:border-r">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
                        <Phone size={16} strokeWidth={2} />
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Telepon</p>
                        <p className="text-sm font-semibold text-foreground mt-0.5">
                            {guardian.phone ?? <span className="font-normal text-muted-foreground">—</span>}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3 p-4">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
                        <IdCard size={16} strokeWidth={2} />
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">NIK</p>
                        <p className="font-mono text-sm font-medium tracking-wider text-foreground mt-0.5">
                            {guardian.nik ?? <span className="font-sans font-normal text-muted-foreground">—</span>}
                        </p>
                    </div>
                </div>
                {guardian.occupation && (
                    <div className="flex items-center gap-3 p-4 border-t border-border/70 sm:col-span-2 bg-muted/50">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-muted-foreground shadow-sm border border-border/70">
                            <Users size={16} strokeWidth={2} />
                        </div>
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Pekerjaan</p>
                            <p className="text-sm font-medium text-foreground mt-0.5">{guardian.occupation}</p>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function SantriProfile({ student }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Saya" />

            <div className="min-h-full bg-muted/50">
                <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">

                    <div className="mb-6">
                        <h1 className="text-3xl font-bold text-foreground tracking-tight">Profil Santri</h1>
                        <p className="text-muted-foreground mt-1">
                            Kelola informasi pribadi dan data akademik Anda.
                        </p>
                    </div>

                    <div className="mb-8">
                        <ProfileHero
                            student={student}
                            editHref="/santri/profile/edit"
                        />
                    </div>

                    <div className="flex flex-col gap-6">
                        {/* Informasi Pribadi */}
                        <ProfileSectionCard icon={User} title="Informasi Pribadi" subtitle="Data dasar santri yang tercatat di sistem akademik">
                            <ProfileInfoRow icon={User}     label="Nama Lengkap"  value={student.full_name} />
                            <ProfileInfoRow icon={BookOpen} label="NIS"           value={student.nis} mono />
                            <ProfileInfoRow icon={IdCard}   label="NIK"           value={student.nik} mono />
                            <ProfileInfoRow icon={Users}    label="Jenis Kelamin" value={student.gender === 'L' ? 'Laki-laki' : 'Perempuan'} />
                            <ProfileInfoRow icon={Home}     label="Tempat Lahir"  value={student.birth_place} />
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
                            <ProfileInfoRow icon={Home}     label="Alamat"        value={student.address} />
                            <ProfileInfoRow icon={BookOpen} label="Kelas"         value={student.current_class?.name} />
                            <div className="group flex flex-col gap-1 py-3.5 sm:flex-row sm:items-center sm:gap-4">
                                <div className="flex items-center gap-3 sm:w-52 sm:shrink-0">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                        <BookOpen size={16} strokeWidth={2} />
                                    </div>
                                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</span>
                                </div>
                                <div className="sm:flex-1">
                                    <Badge className="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border-none shadow-none font-medium">
                                        {student.status}
                                    </Badge>
                                </div>
                            </div>
                        </ProfileSectionCard>

                        {/* Data EMIS */}
                        <EmProfileView emProfile={student.emis_profile} compact />

                        {/* Data Wali */}
                        {student.guardians && student.guardians.length > 0 && (
                            <ProfileSectionCard
                                icon={Shield}
                                title="Informasi Wali"
                                subtitle="Orang tua atau wali santri yang bertanggung jawab"
                                action={
                                    <Link
                                        href="/santri/profile/edit"
                                        className="text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:underline"
                                    >
                                        Edit Wali
                                    </Link>
                                }
                            >
                                {student.guardians.map((g, idx) => (
                                    <GuardianCard key={g.id} guardian={g} index={idx + 1} />
                                ))}
                            </ProfileSectionCard>
                        )}
                    </div>

                    <div className="h-12" />
                </div>
            </div>
        </AppLayout>
    );
}
