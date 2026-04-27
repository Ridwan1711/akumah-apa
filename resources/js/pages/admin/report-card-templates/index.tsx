import { Head, Link, router } from '@inertiajs/react';
import { FileText, LayoutTemplate, Pencil, Plus, Star } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ReportCardTemplate = {
    id: number;
    name: string;
    is_default: boolean;
    config: Record<string, unknown>;
};

type Props = {
    templates: ReportCardTemplate[];
};

export default function ReportCardTemplatesIndex({ templates }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Raport', href: '/admin/report-cards' },
        { title: 'Template Raport', href: '/admin/report-card-templates' },
    ];

    function setDefault(id: number) {
        router.post(`/admin/report-card-templates/${id}/set-default`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Template Raport" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Template Raport" description="Kelola layout dan styling template raport diniyah" />
                    <Button asChild>
                        <Link href="/admin/report-card-templates/create">
                            <Plus className="mr-2 size-4" /> Buat Template Baru
                        </Link>
                    </Button>
                </div>
                <FlashMessage />

                {templates.length === 0 ? (
                    <div className="rounded-lg border p-8 text-center text-muted-foreground">
                        <FileText className="mx-auto mb-2 size-8" />
                        Belum ada template. Buat template pertama untuk mengatur tampilan raport.
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {templates.map((t) => (
                            <div key={t.id} className="rounded-lg border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <h3 className="font-semibold">{t.name}</h3>
                                    {t.is_default ? (
                                        <Badge>Default</Badge>
                                    ) : (
                                        <Button size="sm" variant="ghost" onClick={() => setDefault(t.id)}>
                                            <Star className="mr-1 size-4" /> Jadikan Default
                                        </Button>
                                    )}
                                </div>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    {(t.config?.editor_type === 'canva' ? 'Canva' : 'Blok')} · {((t.config?.layout as string[]) ?? []).length} blok
                                </p>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/report-card-templates/${t.id}/design`}>
                                            <LayoutTemplate className="mr-1 size-4" /> Desain Canva
                                        </Link>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/report-card-templates/${t.id}/edit`}>
                                            <Pencil className="mr-1 size-4" /> Edit Blok
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
