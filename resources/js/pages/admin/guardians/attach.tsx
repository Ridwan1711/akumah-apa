import { Head, Link, useForm } from '@inertiajs/react';
import { Search, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Student } from '@/types';

const relationships = ['ayah', 'ibu', 'kakak', 'paman', 'bibi', 'kakek', 'nenek', 'wali', 'lainnya'];

type Props = {
    student: Student;
    existingUsers: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        is_active: boolean;
        roles: string[];
        has_guardian_record: boolean;
        guardian_id: number | null;
        guardian_name: string | null;
        guardian_phone: string | null;
        guardian_email: string | null;
        is_already_attached: boolean;
    }[];
};

export default function GuardianAttach({ student, existingUsers }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Data Santri', href: '/admin/students' },
        { title: student.full_name, href: `/admin/students/${student.id}` },
        { title: 'Tambahkan Wali yang Ada', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        guardian_id: '',
        user_id: '',
        relationship: 'ayah',
    });

    const [guardianSearch, setGuardianSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');
    const [recordFilter, setRecordFilter] = useState<'all' | 'with' | 'without' | 'available' | 'attached'>('all');
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

    const roleOptions = useMemo(() => {
        const roles = new Set<string>();
        existingUsers.forEach((u) => u.roles.forEach((r) => roles.add(r)));
        return Array.from(roles).sort();
    }, [existingUsers]);

    const filteredUsers = useMemo(() => {
        const q = guardianSearch.toLowerCase().trim();
        return existingUsers.filter((u) => {
            if (roleFilter !== 'all' && !u.roles.includes(roleFilter)) {
                return false;
            }
            if (recordFilter === 'with' && !u.has_guardian_record) return false;
            if (recordFilter === 'without' && u.has_guardian_record) return false;
            if (recordFilter === 'available' && u.is_already_attached) return false;
            if (recordFilter === 'attached' && !u.is_already_attached) return false;

            if (!q) return true;

            const haystack = [
                u.name,
                u.email ?? '',
                u.phone ?? '',
                u.guardian_name ?? '',
                u.guardian_phone ?? '',
                ...u.roles,
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(q);
        });
    }, [existingUsers, guardianSearch, roleFilter, recordFilter]);

    const selectedUser = existingUsers.find((u) => u.id === selectedUserId);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(`/admin/students/${student.id}/guardians/attach`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambahkan Wali yang Ada" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading
                    title="Tambahkan Wali yang Sudah Ada"
                    description={`Pilih wali yang sudah terdaftar untuk ditambahkan ke ${student.full_name} (${student.nis})`}
                />

                {existingUsers.length === 0 ? (
                    <div className="rounded-lg border p-6 text-center text-muted-foreground">
                        <UserPlus className="mx-auto mb-2 size-8" />
                        <p>Belum ada user di sistem.</p>
                        <Button variant="outline" className="mt-4" asChild>
                            <Link href={`/admin/students/${student.id}/guardians/create`}>Tambah Wali Baru</Link>
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="max-w-xl space-y-6">
                        <div className="grid gap-2">
                            <Label>Wali *</Label>

                            {/* Search box */}
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama user/guardian, role, telepon, email..."
                                    value={guardianSearch}
                                    onChange={(e) => setGuardianSearch(e.target.value)}
                                    className="pl-8 pr-8"
                                />
                                {guardianSearch && (
                                    <button
                                        type="button"
                                        onClick={() => setGuardianSearch('')}
                                        className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <Select value={roleFilter} onValueChange={setRoleFilter}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Filter role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Role</SelectItem>
                                        {roleOptions.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={recordFilter}
                                    onValueChange={(v) => setRecordFilter(v as typeof recordFilter)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Filter record guardian" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua User</SelectItem>
                                        <SelectItem value="with">Ada Record Guardian</SelectItem>
                                        <SelectItem value="without">Belum Ada Record Guardian</SelectItem>
                                        <SelectItem value="available">Siap Ditautkan</SelectItem>
                                        <SelectItem value="attached">Sudah Tertaut ke Santri Ini</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Guardian list */}
                            <div className="max-h-64 overflow-y-auto rounded-md border divide-y">
                                {filteredUsers.length === 0 ? (
                                    <p className="p-4 text-sm text-center text-muted-foreground">
                                        Tidak ada user yang cocok dengan filter.
                                    </p>
                                ) : (
                                    filteredUsers.map((u) => {
                                        const isSelected = u.id === selectedUserId;
                                        const canAttach = !u.is_already_attached;
                                        return (
                                            <button
                                                key={u.id}
                                                type="button"
                                                onClick={() => {
                                                    if (!canAttach) return;
                                                    if (isSelected) {
                                                        setSelectedUserId(null);
                                                        setData('guardian_id', '');
                                                        setData('user_id', '');
                                                        return;
                                                    }
                                                    setSelectedUserId(u.id);
                                                    if (u.guardian_id) {
                                                        setData('guardian_id', String(u.guardian_id));
                                                        setData('user_id', '');
                                                    } else {
                                                        setData('guardian_id', '');
                                                        setData('user_id', String(u.id));
                                                    }
                                                }}
                                                className={`w-full text-left px-4 py-3 transition-colors ${
                                                    canAttach ? 'hover:bg-muted/50' : 'bg-muted/20'
                                                } ${isSelected ? 'bg-primary/10 border-l-2 border-primary' : ''}`}
                                            >
                                                <div className="font-medium text-sm">
                                                    {u.name}
                                                    {!u.is_active && <span className="ml-2 text-[10px] text-amber-600">(nonaktif)</span>}
                                                </div>
                                                <div className="text-xs text-muted-foreground mt-0.5 flex gap-3">
                                                    {u.phone && <span>{u.phone}</span>}
                                                    {u.email && <span>{u.email}</span>}
                                                    {u.roles.length > 0 && <span>Role: {u.roles.join(', ')}</span>}
                                                </div>
                                                <div className="mt-1 text-xs">
                                                    {u.has_guardian_record ? (
                                                        <span className="text-emerald-700">
                                                            Guardian: {u.guardian_name ?? '-'}
                                                            {u.is_already_attached && ' (sudah tertaut ke santri ini)'}
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-700">Belum ada record guardian (akan dibuat otomatis dari user ini).</span>
                                                    )}
                                                </div>
                                            </button>
                                        );
                                    })
                                )}
                            </div>

                            {selectedUser && (
                                <p className="text-sm text-primary font-medium">
                                    Dipilih: {selectedUser.name}
                                </p>
                            )}

                            <InputError message={errors.guardian_id} />
                            <InputError message={errors.user_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Hubungan dengan santri ini *</Label>
                            <Select value={data.relationship} onValueChange={(v) => setData('relationship', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {relationships.map((r) => (
                                        <SelectItem key={r} value={r} className="capitalize">
                                            {r}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.relationship} />
                        </div>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={processing || (!data.guardian_id && !data.user_id)}>
                                {processing && <Spinner />}
                                Tambahkan
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={`/admin/students/${student.id}`}>Batal</Link>
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={`/admin/students/${student.id}/guardians/create`}>Tambah Wali Baru</Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
