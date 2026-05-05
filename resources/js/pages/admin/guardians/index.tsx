import { Head, Link, router } from '@inertiajs/react';
import { Eye, RotateCcw, Search, UserCheck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import {
    CrudEmptyState,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Guardian, PaginatedData, Student } from '@/types';

type GuardianRow = Pick<Guardian, 'id' | 'full_name' | 'phone' | 'email' | 'user_id' | 'relationship'> & {
    students_count: number;
    students: Pick<Student, 'id' | 'full_name' | 'nis'>[];
};

type Props = {
    guardians: PaginatedData<GuardianRow>;
    filters: { search?: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Data Wali Santri', href: '/admin/guardians' },
];

export default function GuardianIndex({ guardians, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [debouncedSearch, setDebouncedSearch] = useState(search);

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 300);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        if (debouncedSearch !== (filters.search ?? '')) {
            router.get(
                '/admin/guardians',
                { search: debouncedSearch || undefined },
                { preserveState: true, preserveScroll: true },
            );
        }
    }, [debouncedSearch, filters.search]);

    function resetFilters() {
        setSearch('');
        setDebouncedSearch('');
        router.get('/admin/guardians', {}, { preserveState: true, preserveScroll: true });
    }

    const withAccount = guardians.data.filter((g) => g.user_id).length;
    const withStudents = guardians.data.filter((g) => g.students_count > 0).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Wali Santri" />
            <div>
                <CrudPageHeader
                    title="Data Wali Santri"
                    description="Daftar semua wali santri yang terdaftar di sistem beserta informasi santri yang diasuh."
                />

                <CrudStatStrip
                    items={[
                        {
                            key: 'total',
                            label: 'Total Wali',
                            value: guardians.total,
                            icon: <Users size={18} />,
                            tone: 'blue',
                        },
                        {
                            key: 'with-account',
                            label: 'Punya Akun',
                            value: withAccount,
                            icon: <UserCheck size={18} />,
                            tone: 'green',
                        },
                        {
                            key: 'with-students',
                            label: 'Punya Santri',
                            value: withStudents,
                            icon: <Users size={18} />,
                            tone: 'amber',
                        },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={
                        <>
                            <div className="mcr-search">
                                <Search size={15} />
                                <input
                                    placeholder="Cari nama, telepon, NIK..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            {search.trim().length > 0 ? (
                                <button type="button" className="mcr-btn ghost" onClick={resetFilters}>
                                    <RotateCcw size={14} />
                                    Reset
                                </button>
                            ) : null}
                        </>
                    }
                />

                <CrudTableShell>
                    <table className="mcr-table">
                        <thead>
                            <tr>
                                <th>Nama Wali</th>
                                <th>Telepon</th>
                                <th>Email</th>
                                <th>Santri Diasuh</th>
                                <th>Akun</th>
                                <th style={{ textAlign: 'right' }}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {guardians.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <CrudEmptyState
                                            title="Tidak ada data wali santri"
                                            description="Tambah wali santri melalui halaman detail santri."
                                        />
                                    </td>
                                </tr>
                            ) : (
                                guardians.data.map((guardian) => (
                                    <tr key={guardian.id}>
                                        <td>
                                            <div className="mcr-student-cell">
                                                <span className="mcr-avatar">
                                                    {guardian.full_name
                                                        .split(' ')
                                                        .slice(0, 2)
                                                        .map((part) => part[0] ?? '')
                                                        .join('')
                                                        .toUpperCase()}
                                                </span>
                                                <div>
                                                    <div className="name">{guardian.full_name}</div>
                                                    {guardian.relationship && (
                                                        <div className="sub capitalize">{guardian.relationship}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td>{guardian.phone ?? '-'}</td>
                                        <td>{guardian.email ?? '-'}</td>
                                        <td>
                                            {guardian.students.length === 0 ? (
                                                <span className="text-muted-foreground text-sm">-</span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {guardian.students.map((s) => (
                                                        <Link
                                                            key={s.id}
                                                            href={`/admin/students/${s.id}`}
                                                            className="mcr-btn ghost"
                                                            style={{ fontSize: 11, padding: '2px 6px' }}
                                                        >
                                                            {s.full_name}
                                                        </Link>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            <Badge variant={guardian.user_id ? 'default' : 'outline'}>
                                                {guardian.user_id ? 'Aktif' : 'Belum Ada'}
                                            </Badge>
                                        </td>
                                        <td>
                                            <div className="mcr-action-group" style={{ justifyContent: 'flex-end' }}>
                                                {guardian.students.length > 0 && (
                                                    <Link
                                                        href={`/admin/students/${guardian.students[0]!.id}/guardians/${guardian.id}/edit`}
                                                        className="mcr-icon-action"
                                                        title="Edit via santri pertama"
                                                    >
                                                        <Eye size={13} />
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CrudTableShell>

                <CrudPagination links={guardians.links} />
            </div>
        </AppLayout>
    );
}
