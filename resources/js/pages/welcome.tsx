import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, GraduationCap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="SIAKAD Pesantren" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6">
                <div className="w-full max-w-md text-center">
                    <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-primary">
                        <GraduationCap className="size-8 text-primary-foreground" />
                    </div>

                    <h1 className="mb-2 text-3xl font-bold tracking-tight">
                        SIAKAD Pesantren
                    </h1>
                    <p className="mb-8 text-muted-foreground">
                        Sistem Informasi Akademik Pesantren
                    </p>

                    {auth?.user ? (
                        <Button asChild size="lg" className="w-full">
                            <Link href="/dashboard">Masuk ke Dashboard</Link>
                        </Button>
                    ) : (
                        <Button asChild size="lg" className="w-full">
                            <Link href="/login">Masuk</Link>
                        </Button>
                    )}

                    <div className="mt-12 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                        <BookOpen className="size-3" />
                        <span>Al-Manhood Islamic Boarding School</span>
                    </div>
                </div>
            </div>
        </>
    );
}
