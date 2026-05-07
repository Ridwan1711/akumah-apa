import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { AppSelect  } from '@/components/manhood';
import type {SelectOption} from '@/components/manhood';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Data Santri', href: '/admin/students' },
    { title: 'Tambah Santri', href: '/admin/students/create' },
];

export default function StudentCreate() {
    const { data, setData, post, processing, errors } = useForm({
        user_id: '',
        full_name: '',
        admission_year: String(new Date().getFullYear()),
    });

    const [existingUserOptions, setExistingUserOptions] = useState<SelectOption[]>([]);
    const [isLoadingExistingUsers, setIsLoadingExistingUsers] = useState(false);
    const selectedExistingUserOption = existingUserOptions.find((item) => item.value === data.user_id) ?? null;

    async function loadEligibleUsers(searchTerm = '') {
        setIsLoadingExistingUsers(true);
        try {
            const url = new URL('/admin/students/eligible-users', window.location.origin);
            if (searchTerm.trim() !== '') {
                url.searchParams.set('search', searchTerm.trim());
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            const options: SelectOption[] = (payload?.data ?? []).map((user: { id: number; name: string; email: string }) => ({
                value: String(user.id),
                label: `${user.name} (${user.email})`,
            }));
            setExistingUserOptions(options);
        } catch {
            setExistingUserOptions([]);
        } finally {
            setIsLoadingExistingUsers(false);
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/students');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Santri" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Heading title="Tambah Santri" description="Isi data santri baru" />
                <p className="text-sm text-muted-foreground">
                    Admin hanya mengisi data awal. Data profil lain akan dilengkapi oleh santri.
                </p>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="existing-user">Pilih User Existing (Opsional)</Label>
                        <AppSelect
                            inputId="existing-user"
                            placeholder="Cari user..."
                            options={existingUserOptions}
                            isLoading={isLoadingExistingUsers}
                            value={selectedExistingUserOption}
                            onChange={(option) => setData('user_id', String(option?.value ?? ''))}
                            onInputChange={(value, meta) => {
                                if (meta.action === 'input-change') {
                                    void loadEligibleUsers(value);
                                }
                                return value;
                            }}
                            onMenuOpen={() => {
                                if (existingUserOptions.length === 0) {
                                    void loadEligibleUsers();
                                }
                            }}
                        />
                        <InputError message={errors.user_id} />
                    </div>

                    <p className="text-xs text-muted-foreground">NIS dan NISM dibuat otomatis oleh sistem saat santri disimpan.</p>

                    <div className="grid gap-2">
                        <Label htmlFor="full_name">Nama Lengkap *</Label>
                        <Input id="full_name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} required />
                        <InputError message={errors.full_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admission_year">Tahun Masuk *</Label>
                        <Input id="admission_year" type="number" min={2000} max={2099} value={data.admission_year} onChange={(e) => setData('admission_year', e.target.value)} required />
                        <InputError message={errors.admission_year} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/students">Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
