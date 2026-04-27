import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

export default function ForceChangePassword() {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/force-change-password');
    }

    return (
        <AuthLayout
            title="Ganti Password"
            description="Anda harus mengganti password sebelum melanjutkan"
        >
            <Head title="Ganti Password" />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password Baru</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            autoFocus
                            placeholder="Minimal 8 karakter"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                            placeholder="Ulangi password baru"
                        />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <Spinner />}
                        Simpan Password Baru
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
