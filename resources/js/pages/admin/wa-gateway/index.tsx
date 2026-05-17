import { Head } from '@inertiajs/react';
import axios from 'axios';
import { MessageCircle, RefreshCw, Unplug } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import { CrudCard, CrudPageHeader } from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WaSessionRow = {
    id: number;
    slug: string;
    label: string;
    description: string | null;
    status: string;
    linked_phone: string | null;
    qr_data_url?: string | null;
    has_qr: boolean;
    qr_updated_at: string | null;
    last_ready_at: string | null;
    last_error: string | null;
    is_enabled: boolean;
};

type Props = {
    sessions: WaSessionRow[];
    gateway_base_url: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'WhatsApp Gateway', href: '/admin/wa-gateway' },
];

function statusLabel(status: string): string {
    switch (status) {
        case 'ready':
            return 'Terhubung';
        case 'pairing':
            return 'Scan QR';
        case 'auth_failure':
            return 'Gagal auth';
        default:
            return 'Terputus';
    }
}

function statusClass(status: string): string {
    switch (status) {
        case 'ready':
            return 'bg-emerald-100 text-emerald-800';
        case 'pairing':
            return 'bg-amber-100 text-amber-800';
        case 'auth_failure':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

export default function WaGatewayIndex({ sessions: initialSessions, gateway_base_url }: Props) {
    const [sessions, setSessions] = useState(initialSessions);
    const [busySlug, setBusySlug] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const refreshQr = useCallback(async (slug: string) => {
        const { data } = await axios.get(`/admin/wa-gateway/${slug}/qr`);
        const updated = data.session as WaSessionRow;
        setSessions((prev) => prev.map((s) => (s.slug === slug ? { ...s, ...updated } : s)));
    }, []);

    const pairingSlugs = sessions.filter((s) => s.status === 'pairing').map((s) => s.slug).join(',');

    useEffect(() => {
        if (pairingSlugs === '') {
            return;
        }
        const slugs = pairingSlugs.split(',');
        const timer = window.setInterval(() => {
            slugs.forEach((slug) => {
                void refreshQr(slug);
            });
        }, 5000);
        return () => window.clearInterval(timer);
    }, [pairingSlugs, refreshQr]);

    async function handleStart(slug: string) {
        setBusySlug(slug);
        setError(null);
        try {
            const { data } = await axios.post(`/admin/wa-gateway/${slug}/start`);
            const updated = data.session as WaSessionRow;
            setSessions((prev) => prev.map((s) => (s.slug === slug ? { ...s, ...updated } : s)));
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : 'Gagal memulai sesi');
        } finally {
            setBusySlug(null);
        }
    }

    async function handleLogout(slug: string) {
        if (!window.confirm(`Putuskan sesi ${slug}?`)) {
            return;
        }
        setBusySlug(slug);
        setError(null);
        try {
            const { data } = await axios.post(`/admin/wa-gateway/${slug}/logout`);
            const updated = data.session as WaSessionRow;
            setSessions((prev) => prev.map((s) => (s.slug === slug ? { ...s, ...updated } : s)));
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : 'Gagal logout sesi');
        } finally {
            setBusySlug(null);
        }
    }

    async function handleRefreshStatus(slug: string) {
        setBusySlug(slug);
        try {
            const { data } = await axios.get(`/admin/wa-gateway/${slug}/status`);
            const updated = data.session as WaSessionRow;
            setSessions((prev) => prev.map((s) => (s.slug === slug ? { ...s, ...updated } : s)));
        } finally {
            setBusySlug(null);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="WhatsApp Gateway" />
            <div className="space-y-4">
                <CrudPageHeader
                    title="WhatsApp Gateway"
                    description={`Kelola koneksi multi-akun WA (scan QR dari panel ini). Gateway: ${gateway_base_url}`}
                />
                <FlashMessage />
                {error ? <p className="text-sm text-red-600">{error}</p> : null}

                <div className="grid gap-4 md:grid-cols-3">
                    {sessions.map((session) => (
                        <CrudCard key={session.slug} className="p-4 space-y-3">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="flex items-center gap-2 font-semibold text-slate-800">
                                        <MessageCircle size={18} />
                                        {session.label}
                                    </div>
                                    <p className="text-xs text-slate-500 mt-1">{session.slug}</p>
                                    {session.description ? (
                                        <p className="text-xs text-slate-600 mt-2">{session.description}</p>
                                    ) : null}
                                </div>
                                <span className={`text-xs px-2 py-1 rounded-full shrink-0 ${statusClass(session.status)}`}>
                                    {statusLabel(session.status)}
                                </span>
                            </div>

                            {session.linked_phone ? (
                                <p className="text-sm">
                                    Nomor terhubung: <strong>{session.linked_phone}</strong>
                                </p>
                            ) : null}

                            {session.last_error ? (
                                <p className="text-xs text-red-600">{session.last_error}</p>
                            ) : null}

                            {session.qr_data_url ? (
                                <div className="flex flex-col items-center gap-2 rounded-lg border bg-white p-3">
                                    <p className="text-xs text-center text-slate-600">
                                        Buka WhatsApp → Perangkat tertaut → Scan QR
                                    </p>
                                    <img
                                        src={session.qr_data_url}
                                        alt={`QR ${session.slug}`}
                                        className="h-52 w-52"
                                    />
                                </div>
                            ) : null}

                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    className="mcr-btn mcr-btn-primary text-xs"
                                    disabled={busySlug === session.slug}
                                    onClick={() => void handleStart(session.slug)}
                                >
                                    {busySlug === session.slug ? 'Memproses...' : 'Hubungkan'}
                                </button>
                                <button
                                    type="button"
                                    className="mcr-btn mcr-btn-secondary text-xs inline-flex items-center gap-1"
                                    disabled={busySlug === session.slug}
                                    onClick={() => void handleRefreshStatus(session.slug)}
                                >
                                    <RefreshCw size={14} />
                                    Status
                                </button>
                                <button
                                    type="button"
                                    className="mcr-btn mcr-btn-danger text-xs inline-flex items-center gap-1"
                                    disabled={busySlug === session.slug}
                                    onClick={() => void handleLogout(session.slug)}
                                >
                                    <Unplug size={14} />
                                    Putuskan
                                </button>
                            </div>
                        </CrudCard>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}