import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ImagePlus, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const BLOCK_LABELS: Record<string, string> = {
    header: 'Header & Logo',
    info: 'Info Santri',
    grades: 'Nilai Kitab',
    tahfidz: 'Progress Tahfidz',
    violations: 'Pelanggaran',
    notes: 'Catatan Wali Kelas',
    footer: 'Footer & Tanda Tangan',
    qr: 'QR Code Verifikasi',
};

const DEFAULT_LAYOUT = ['header', 'info', 'grades', 'tahfidz', 'violations', 'notes', 'footer', 'qr'];

type ReportCardTemplate = {
    id: number;
    name: string;
    is_default: boolean;
    config: {
        layout: string[];
        blocks: Record<string, { visible?: boolean }>;
        style: Record<string, string | number>;
        images: Record<string, string | null>;
    };
};

type AssetItem = { path: string; url: string; name: string };

type Props = {
    template: ReportCardTemplate | null;
    isCreate: boolean;
};

export default function ReportCardTemplateEdit({ template, isCreate }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Raport', href: '/admin/report-cards' },
        { title: 'Template Raport', href: '/admin/report-card-templates' },
        { title: isCreate ? 'Buat Template' : `Edit: ${template?.name}`, href: '#' },
    ];

    const defaultConfig = {
        layout: template?.config?.layout ?? DEFAULT_LAYOUT,
        blocks: template?.config?.blocks ?? Object.fromEntries(DEFAULT_LAYOUT.map((b) => [b, { visible: true }])),
        style: {
            font_family: template?.config?.style?.font_family ?? 'DejaVu Sans',
            font_size: template?.config?.style?.font_size ?? 11,
            primary_color: template?.config?.style?.primary_color ?? '#1a1a1a',
            header_bg: template?.config?.style?.header_bg ?? '#f0f0f0',
            header_border_color: template?.config?.style?.header_border_color ?? '#333',
        },
        images: {
            logo: template?.config?.images?.logo ?? null,
            signature_wali: template?.config?.images?.signature_wali ?? null,
            signature_kepala: template?.config?.images?.signature_kepala ?? null,
            stamp: template?.config?.images?.stamp ?? null,
        },
    };

    const form = useForm({
        name: template?.name ?? '',
        is_default: template?.is_default ?? false,
        config: defaultConfig,
    });

    const [assets, setAssets] = useState<{ logos: AssetItem[]; signatures: AssetItem[]; stamps: AssetItem[] }>({
        logos: [],
        signatures: [],
        stamps: [],
    });
    const [uploading, setUploading] = useState<string | null>(null);

    const loadAssets = useCallback(async () => {
        const res = await fetch('/admin/report-card-assets/list');
        const data = await res.json();
        setAssets(data);
    }, []);

    useEffect(() => {
        loadAssets();
    }, [loadAssets]);

    function moveBlock(index: number, dir: 'up' | 'down') {
        const layout = [...form.data.config.layout];
        const ni = dir === 'up' ? index - 1 : index + 1;
        if (ni < 0 || ni >= layout.length) return;
        [layout[index], layout[ni]] = [layout[ni], layout[index]];
        form.setData('config', { ...form.data.config, layout });
    }

    function toggleBlockVisible(block: string) {
        const blocks = { ...form.data.config.blocks };
        blocks[block] = { ...blocks[block], visible: !(blocks[block]?.visible ?? true) };
        form.setData('config', { ...form.data.config, blocks });
    }

    function setStyle(key: string, value: string | number) {
        const style = { ...form.data.config.style, [key]: value };
        form.setData('config', { ...form.data.config, style });
    }

    function setImage(key: string, path: string | null) {
        const images = { ...form.data.config.images, [key]: path };
        form.setData('config', { ...form.data.config, images });
    }

    async function handleUpload(type: 'logo' | 'signature_wali' | 'signature_kepala' | 'stamp', e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(type);
        const fd = new FormData();
        fd.append('file', file);
        fd.append('type', type);
        const csrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        const token = csrf ? decodeURIComponent(csrf) : '';
        try {
            const res = await fetch('/admin/report-card-assets/upload', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
            });
            const data = await res.json();
            if (data.path) {
                setImage(type, data.path);
                loadAssets();
            }
        } finally {
            setUploading(null);
        }
        e.target.value = '';
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (isCreate) {
            form.post('/admin/report-card-templates');
        } else {
            form.put(`/admin/report-card-templates/${template!.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isCreate ? 'Buat Template Raport' : `Edit Template: ${template?.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title={isCreate ? 'Buat Template Raport' : `Edit: ${template?.name}`} description="Atur layout, styling, dan gambar template raport" />
                <FlashMessage />

                <form onSubmit={handleSubmit} className="space-y-8">
                    <div className="rounded-lg border p-4">
                        <h3 className="mb-3 font-semibold">Informasi Dasar</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Nama Template</Label>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Template Raport 2024" />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="flex items-center space-x-2 pt-8">
                                <Checkbox checked={form.data.is_default} onCheckedChange={(v) => form.setData('is_default', v === true)} />
                                <Label>Jadikan template default</Label>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border p-4">
                        <h3 className="mb-3 font-semibold">Urutan Blok</h3>
                        <p className="mb-3 text-sm text-muted-foreground">Atur urutan tampilan blok di raport. Geser dengan tombol.</p>
                        <div className="space-y-2">
                            {form.data.config.layout.map((block: string, i: number) => (
                                <div key={block} className="flex items-center gap-2 rounded border bg-muted/30 px-3 py-2">
                                    <Button type="button" variant="ghost" size="icon" onClick={() => moveBlock(i, 'up')} disabled={i === 0}>
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => moveBlock(i, 'down')} disabled={i === form.data.config.layout.length - 1}>
                                        <ChevronDown className="size-4" />
                                    </Button>
                                    <Checkbox
                                        checked={form.data.config.blocks[block]?.visible ?? true}
                                        onCheckedChange={() => toggleBlockVisible(block)}
                                    />
                                    <span className="flex-1 font-medium">{BLOCK_LABELS[block] ?? block}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border p-4">
                        <h3 className="mb-3 font-semibold">Styling</h3>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="grid gap-2">
                                <Label>Font</Label>
                                <Input value={form.data.config.style.font_family} onChange={(e) => setStyle('font_family', e.target.value)} placeholder="DejaVu Sans" />
                            </div>
                            <div className="grid gap-2">
                                <Label>Ukuran Font (px)</Label>
                                <Input type="number" min={8} max={16} value={form.data.config.style.font_size} onChange={(e) => setStyle('font_size', parseInt(e.target.value) || 11)} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Warna Teks</Label>
                                <Input type="color" value={form.data.config.style.primary_color} onChange={(e) => setStyle('primary_color', e.target.value)} className="h-10 w-20 cursor-pointer" />
                                <Input value={form.data.config.style.primary_color} onChange={(e) => setStyle('primary_color', e.target.value)} className="font-mono text-sm" />
                            </div>
                            <div className="grid gap-2">
                                <Label>Background Header</Label>
                                <Input type="color" value={form.data.config.style.header_bg} onChange={(e) => setStyle('header_bg', e.target.value)} className="h-10 w-20 cursor-pointer" />
                            </div>
                            <div className="grid gap-2">
                                <Label>Border Header</Label>
                                <Input type="color" value={form.data.config.style.header_border_color} onChange={(e) => setStyle('header_border_color', e.target.value)} className="h-10 w-20 cursor-pointer" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border p-4">
                        <h3 className="mb-3 font-semibold">Gambar</h3>
                        <p className="mb-3 text-sm text-muted-foreground">Upload logo, tanda tangan, dan stempel untuk raport.</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {(['logo', 'signature_wali', 'signature_kepala', 'stamp'] as const).map((key) => (
                                <div key={key} className="grid gap-2">
                                    <Label>
                                        {key === 'logo' && 'Logo'}
                                        {key === 'signature_wali' && 'Tanda Tangan Wali Kelas'}
                                        {key === 'signature_kepala' && 'Tanda Tangan Kepala Madrasah'}
                                        {key === 'stamp' && 'Stempel'}
                                    </Label>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <label className="inline-block cursor-pointer">
                                            <input type="file" accept="image/*" className="hidden" onChange={(e) => handleUpload(key, e)} disabled={!!uploading} />
                                            <span className="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium ring-offset-background hover:bg-accent hover:text-accent-foreground">
                                                {uploading === key ? <Loader2 className="mr-1 size-4 animate-spin" /> : <ImagePlus className="mr-1 size-4" />}
                                                Upload
                                            </span>
                                        </label>
                                        <Select value={form.data.config.images[key] ?? ''} onValueChange={(v) => setImage(key, v || null)}>
                                            <SelectTrigger className="min-w-[180px]"><SelectValue placeholder="Pilih file" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="_none">Tidak ada</SelectItem>
                                                {(key === 'logo' ? assets.logos : key.includes('signature') ? assets.signatures : assets.stamps).map((a) => (
                                                    <SelectItem key={a.path} value={a.path}>{a.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {form.data.config.images[key] && (() => {
                                        const asset = [...assets.logos, ...assets.signatures, ...assets.stamps].find((a) => a.path === form.data.config.images[key]);
                                        return asset ? <img src={asset.url} alt="" className="mt-1 h-16 rounded border object-contain" /> : null;
                                    })()}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={form.processing}>{form.processing && <Spinner />}Simpan</Button>
                        {!isCreate && (
                            <Button variant="outline" asChild>
                                <Link href={`/admin/report-card-templates/${template!.id}/design`}>
                                    Editor Canva (Edit Teks Bebas)
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild><Link href="/admin/report-card-templates">Batal</Link></Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
