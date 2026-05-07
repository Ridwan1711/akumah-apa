import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit(),
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Profile information"
                        description="Update your name and email address"
                    />

                    <div className="space-y-4 rounded-lg border p-4">
                        <Heading
                            variant="small"
                            title="Foto Profil"
                            description="Foto aktif memakai fallback: kustom -> resmi."
                        />
                        {auth.user.profile_photo_url ? (
                            <img
                                src={auth.user.profile_photo_url}
                                alt="Foto profil aktif"
                                className="h-24 w-24 rounded-full border object-cover"
                            />
                        ) : (
                            <div className="flex h-24 w-24 items-center justify-center rounded-full border text-xs text-muted-foreground">
                                No Photo
                            </div>
                        )}
                        <Form
                            action="/settings/profile/photo"
                            method="post"
                            options={{ preserveScroll: true }}
                            className="space-y-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="type" value="official" />
                                    <Label htmlFor="official-photo">Foto Resmi {auth.user.has_official_photo ? '(Terkunci untuk non-admin)' : '(Wajib)'}</Label>
                                    <Input id="official-photo" type="file" name="photo" accept="image/*" />
                                    <InputError message={errors.photo} />
                                    <Button disabled={processing}>Upload Foto Resmi</Button>
                                </>
                            )}
                        </Form>
                        <Form
                            action="/settings/profile/photo"
                            method="post"
                            options={{ preserveScroll: true }}
                            className="space-y-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="type" value="custom" />
                                    <Label htmlFor="custom-photo">Foto Kustom (Opsional)</Label>
                                    <Input id="custom-photo" type="file" name="photo" accept="image/*" />
                                    <InputError message={errors.photo} />
                                    <Button disabled={processing}>Upload Foto Kustom</Button>
                                </>
                            )}
                        </Form>
                        <Form
                            action="/settings/profile/photo/custom"
                            method="delete"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button type="submit" variant="outline" disabled={processing}>
                                    Hapus Foto Kustom (Fallback ke Resmi)
                                </Button>
                            )}
                        </Form>
                    </div>

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
