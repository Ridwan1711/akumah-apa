import { Head, router } from '@inertiajs/react';
import { AlertTriangle, FileSearch, Filter, ShieldAlert } from 'lucide-react';
import FlashMessage from '@/components/flash-message';
import {
    CrudCard,
    CrudEmptyState,
    CrudPageHeader,
    CrudPagination,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, SystemLog } from '@/types';

type Props = {
    logs: PaginatedData<SystemLog>;
    levels: string[];
    channels: string[];
    filters: {
        level?: string;
        channel?: string;
        search?: string;
        date_from?: string;
        date_to?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Log Sistem', href: '/admin/system-logs' },
];

function levelBadgeClass(level: string): 'active' | 'alumni' | 'keluar' {
    const normalized = level.toLowerCase();
    if (normalized === 'error' || normalized === 'critical' || normalized === 'emergency' || normalized === 'alert') {
        return 'keluar';
    }
    if (normalized === 'warning') {
        return 'alumni';
    }

    return 'active';
}

export default function SystemLogIndex({ logs, levels, channels, filters }: Props) {
    function applyFilters(next: Partial<Props['filters']>) {
        router.get('/admin/system-logs', { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    }

    const activeFilters = [filters.level, filters.channel, filters.search, filters.date_from, filters.date_to].filter(Boolean).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Log Sistem" />
            <div>
                <CrudPageHeader
                    title="Log Sistem"
                    description="Daftar error dan kejadian runtime aplikasi. Buka detail per baris untuk context, URL, dan stack trace."
                />
                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Log', value: logs.total, icon: <FileSearch size={18} />, tone: 'blue' },
                        { key: 'levels', label: 'Jenis Level', value: levels.length, icon: <ShieldAlert size={18} />, tone: 'amber' },
                        { key: 'channels', label: 'Channel', value: channels.length, icon: <AlertTriangle size={18} />, tone: 'purple' },
                        { key: 'filters', label: 'Filter Aktif', value: activeFilters, icon: <Filter size={18} />, tone: 'green' },
                    ]}
                />

                <FlashMessage />

                <CrudToolbar
                    left={(
                        <>
                            <select className="mcr-filter-select" value={filters.level ?? 'all'} onChange={(e) => applyFilters({ level: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Level</option>
                                {levels.map((level) => (
                                    <option key={level} value={level}>{level.toUpperCase()}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.channel ?? 'all'} onChange={(e) => applyFilters({ channel: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Channel</option>
                                {channels.map((channel) => (
                                    <option key={channel} value={channel}>{channel}</option>
                                ))}
                            </select>
                            <input
                                className="mcr-input"
                                placeholder="Cari pesan / URL / IP"
                                value={filters.search ?? ''}
                                onChange={(e) => applyFilters({ search: e.target.value || undefined })}
                            />
                            <input type="date" className="mcr-input" value={filters.date_from ?? ''} onChange={(e) => applyFilters({ date_from: e.target.value || undefined })} />
                            <input type="date" className="mcr-input" value={filters.date_to ?? ''} onChange={(e) => applyFilters({ date_to: e.target.value || undefined })} />
                            <button type="button" className="mcr-btn ghost" onClick={() => router.get('/admin/system-logs', {}, { preserveState: true, preserveScroll: true })}>
                                Reset
                            </button>
                        </>
                    )}
                />

                <CrudCard>
                    {logs.data.length === 0 ? (
                        <CrudEmptyState title="Belum ada log sistem" description="Tidak ada data log sesuai filter yang dipilih." />
                    ) : (
                        <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                            {logs.data.map((log) => (
                                <li
                                    key={log.id}
                                    style={{
                                        borderBottom: '1px solid var(--mhs-border-2, #e5e7eb)',
                                        padding: '16px 18px',
                                    }}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                        <span className={`mcr-dot-badge ${levelBadgeClass(log.level)}`}>{log.level.toUpperCase()}</span>
                                        <span className="mcr-dot-badge alumni">{log.channel}</span>
                                        <span style={{ fontSize: '0.8rem', color: 'var(--mhs-text-muted, #64748b)' }}>
                                            {new Date(log.logged_at).toLocaleString('id-ID')}
                                        </span>
                                    </div>

                                    <p style={{ margin: 0, fontSize: '0.98rem', lineHeight: 1.5, fontWeight: 600 }}>{log.message}</p>

                                    <p style={{ margin: '8px 0 0', fontSize: '0.84rem', color: 'var(--mhs-text-muted, #64748b)' }}>
                                        User: {log.user?.name ?? 'system'} · IP: {log.ip_address ?? '-'} · {log.method ?? '-'} {log.url ?? '-'}
                                    </p>

                                    <details style={{ marginTop: 10 }}>
                                        <summary style={{ cursor: 'pointer', fontSize: '0.82rem', fontWeight: 600, color: 'var(--mhs-primary, #2563eb)' }}>
                                            Lihat detail error
                                        </summary>
                                        <div style={{ marginTop: 10, display: 'grid', gap: 10 }}>
                                            <div>
                                                <strong style={{ fontSize: '0.8rem' }}>Context</strong>
                                                <pre style={{ marginTop: 6, fontSize: '0.75rem', overflowX: 'auto', whiteSpace: 'pre-wrap' }}>
                                                    {JSON.stringify(log.context ?? {}, null, 2)}
                                                </pre>
                                            </div>
                                            <div>
                                                <strong style={{ fontSize: '0.8rem' }}>Extra</strong>
                                                <pre style={{ marginTop: 6, fontSize: '0.75rem', overflowX: 'auto', whiteSpace: 'pre-wrap' }}>
                                                    {JSON.stringify(log.extra ?? {}, null, 2)}
                                                </pre>
                                            </div>
                                            <div>
                                                <strong style={{ fontSize: '0.8rem' }}>Stack trace</strong>
                                                <pre style={{ marginTop: 6, fontSize: '0.75rem', overflowX: 'auto', whiteSpace: 'pre-wrap' }}>
                                                    {log.trace ?? '-'}
                                                </pre>
                                            </div>
                                        </div>
                                    </details>
                                </li>
                            ))}
                        </ul>
                    )}
                    <CrudPagination links={logs.links} />
                </CrudCard>
            </div>
        </AppLayout>
    );
}
