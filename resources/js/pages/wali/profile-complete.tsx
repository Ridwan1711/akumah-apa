import { Head, useForm } from '@inertiajs/react';
import { UserCircle } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
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
import type { BreadcrumbItem, Guardian } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Lengkapi Profil Wali', href: '/wali/profile/complete' },
];

const guardianRelationships = [
    'ayah', 'ibu', 'kakak', 'paman', 'bibi',
    'kakek', 'nenek', 'wali', 'lainnya',
];

type Props = {
    guardian: Guardian;
};

export default function WaliProfileComplete({ guardian }: Props) {
    const form = useForm({
        full_name: guardian.full_name ?? '',
        nik: guardian.nik ?? '',
        phone: guardian.phone ?? '',
        email: guardian.email ?? '',
        occupation: guardian.occupation ?? '',
        income_band: guardian.income_band ?? '',
        relationship: guardian.relationship ?? guardian.pivot?.relationship ?? 'wali',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put('/wali/profile/complete');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Lengkapi Profil Wali" />
            <div className="mx-auto max-w-2xl flex flex-col gap-4 p-4">
                <Heading
                    title="Lengkapi Profil Wali"
                    description="Isi data diri Anda dengan benar sebelum mengakses portal wali santri."
                />
                <FlashMessage />

                <div className="rounded-xl border bg-card p-6 shadow-sm">
                    <div className="mb-6 flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <UserCircle className="size-7" />
                        </div>
                        <div>
                            <p className="font-semibold">Data wali / orang tua</p>
                            <p className="text-sm text-muted-foreground">Data ini digunakan untuk komunikasi dan administrasi.</p>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Nama lengkap <span className="text-destructive">*</span></Label>
                            <Input
                                value={form.data.full_name}
                                onChange={(e) => form.setData('full_name', e.target.value)}
                                required
                            />
                            <InputError message={form.errors.full_name} />
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                            <div className="grid gap-2">
                                <Label>Hubungan dengan santri <span className="text-destructive">*</span></Label>
                                <Select
                                    value={form.data.relationship}
                                    onValueChange={(v) => form.setData('relationship', v)}
                                >
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {guardianRelationships.map((r) => (
                                            <SelectItem key={r} value={r}>{r.charAt(0).toUpperCase() + r.slice(1)}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.relationship} />
                            </div>
                            <div className="grid gap-2">
                                <Label>NIK</Label>
                                <Input
                                    value={form.data.nik}
                                    onChange={(e) => form.setData('nik', e.target.value)}
                                    maxLength={16}
                                />
                                <InputError message={form.errors.nik} />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                            <div className="grid gap-2">
                                <Label>No. HP / WhatsApp</Label>
                                <Input
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                />
                                <InputError message={form.errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                                <InputError message={form.errors.email} />
                            </div>
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                            <div className="grid gap-2">
                                <Label>Pekerjaan</Label>
                                <Input
                                    value={form.data.occupation}
                                    onChange={(e) => form.setData('occupation', e.target.value)}
                                />
                                <InputError message={form.errors.occupation} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Golongan penghasilan</Label>
                                <Input
                                    value={form.data.income_band}
                                    onChange={(e) => form.setData('income_band', e.target.value)}
                                    placeholder="Contoh: di bawah 3 juta"
                                />
                                <InputError message={form.errors.income_band} />
                            </div>
                        </div>

                        <div className="pt-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Simpan & lanjutkan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
