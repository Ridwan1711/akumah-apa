import { Head } from '@inertiajs/react';
import { Activity, FileSearch, Filter, RefreshCcw, ShieldAlert } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    CrudCard,
    CrudEmptyState,
    CrudPageHeader,
    CrudStatStrip,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type LaravelLogEntry = {
    id: string;
    timestamp: string;
    channel: string;
    level: string;
    message: string;
    content: string;
};

type Props = {
    defaultLogPath: string;
};

type FilterState = {
    level: string;
    search: string;
    limit: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Laravel Log', href: '/admin/laravel-logs' },
];

function levelBadgeClass(level: string): 'active' | 'alumni' | 'keluar' {
    const normalized = level.toLowerCase();
    if (normalized === 'error' || normalized === 'critical' || normalized === 'emergency' || normalized === 'alert') {
        return 'keluar';
    }
    if (normalized === 'warning' || normalized === 'notice') {
        return 'alumni';
    }

    return 'active';
}

export default function LaravelLogsIndex({ defaultLogPath }: Props) {
    const [entries, setEntries] = useState<LaravelLogEntry[]>([]);
    const [levels, setLevels] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [lastUpdatedAt, setLastUpdatedAt] = useState<string | null>(null);
    const [errorText, setErrorText] = useState<string | null>(null);
    const [meta, setMeta] = useState<{ exists: boolean; path: string; size: number; modified_at: string | null }>({
        exists: true,
        path: defaultLogPath,
        size: 0,
        modified_at: null,
    });
    const [filters, setFilters] = useState<FilterState>({
        level: 'all',
        search: '',
        limit: 120,
    });

    const loadLogs = useCallback(async () => {
        setLoading(true);
        setErrorText(null);

        try {
            const params = new URLSearchParams({
                level: filters.level,
                search: filters.search,
                limit: String(filters.limit),
            });
            const response = await fetch(`/admin/laravel-logs/data?${params.toString()}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Gagal baca log (${response.status})`);
            }

            const payload = await response.json() as {
                entries: LaravelLogEntry[];
                levels: string[];
                meta: { exists: boolean; path: string; size: number; modified_at: string | null };
            };

            setEntries(payload.entries ?? []);
            setLevels(payload.levels ?? []);
            setMeta(payload.meta ?? { exists: false, path: defaultLogPath, size: 0, modified_at: null });
            setLastUpdatedAt(new Date().toISOString());
        } catch (error) {
            setErrorText(error instanceof Error ? error.message : 'Gagal memuat log');
        } finally {
            setLoading(false);
        }
    }, [defaultLogPath, filters.level, filters.search, filters.limit]);

    useEffect(() => {
        void loadLogs();
    }, [loadLogs]);

    useEffect(() => {
        if (!autoRefresh) return;

        const timer = setInterval(() => {
            void loadLogs();
        }, 5000);

        return () => clearInterval(timer);
    }, [autoRefresh, loadLogs]);

    const activeFilters = useMemo(() => {
        return [filters.level !== 'all', filters.search.trim() !== '', filters.limit !== 120].filter(Boolean).length;
    }, [filters.level, filters.search, filters.limit]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laravel Log" />
            <div>
                <CrudPageHeader
                    title="Laravel Log (Raw)"
                    description="Pantau log mentah dari storage/logs/laravel.log secara realtime untuk investigasi error production."
                />

                <CrudStatStrip
                    items={[
                        { key: 'entries', label: 'Entry Ditampilkan', value: entries.length, icon: <FileSearch size={18} />, tone: 'blue' },
                        { key: 'levels', label: 'Varian Level', value: levels.length, icon: <ShieldAlert size={18} />, tone: 'amber' },
                        { key: 'filter', label: 'Filter Aktif', value: activeFilters, icon: <Filter size={18} />, tone: 'green' },
                        { key: 'refresh', label: 'Auto Refresh', value: autoRefresh ? 'ON' : 'OFF', icon: <Activity size={18} />, tone: 'purple' },
                    ]}
                />

                <CrudToolbar
                    left={(
                        <>
                            <select
                                className="mcr-filter-select"
                                value={filters.level}
                                onChange={(e) => setFilters((prev) => ({ ...prev, level: e.target.value }))}
                            >
                                <option value="all">Semua Level</option>
                                {levels.map((level) => (
                                    <option key={level} value={level}>
                                        {level.toUpperCase()}
                                    </option>
                                ))}
                            </select>

                            <input
                                className="mcr-input"
                                placeholder="Cari keyword (message/trace/context)"
                                value={filters.search}
                                onChange={(e) => setFilters((prev) => ({ ...prev, search: e.target.value }))}
                            />

                            <select
                                className="mcr-filter-select"
                                value={String(filters.limit)}
                                onChange={(e) => setFilters((prev) => ({ ...prev, limit: Number(e.target.value) }))}
                            >
                                <option value="80">80 baris</option>
                                <option value="120">120 baris</option>
                                <option value="200">200 baris</option>
                                <option value="300">300 baris</option>
                            </select>

                            <button type="button" className="mcr-btn ghost" onClick={() => setAutoRefresh((v) => !v)}>
                                {autoRefresh ? 'Pause Realtime' : 'Resume Realtime'}
                            </button>

                            <button type="button" className="mcr-btn ghost" onClick={() => void loadLogs()}>
                                <RefreshCcw size={15} style={{ marginRight: 6 }} />
                                Refresh
                            </button>
                        </>
                    )}
                />

                <CrudCard>
                    <div style={{ padding: '14px 18px', borderBottom: '1px solid var(--mhs-border-2, #e5e7eb)', fontSize: '0.82rem', color: 'var(--mhs-text-muted, #64748b)' }}>
                        File: <strong>{meta.path}</strong>
                        {' · '}
                        Status: <strong>{meta.exists ? 'ada' : 'tidak ada'}</strong>
                        {' · '}
                        Ukuran: <strong>{meta.size.toLocaleString('id-ID')} byte</strong>
                        {' · '}
                        Last modified: <strong>{meta.modified_at ? new Date(meta.modified_at).toLocaleString('id-ID') : '-'}</strong>
                        {' · '}
                        Last fetch: <strong>{lastUpdatedAt ? new Date(lastUpdatedAt).toLocaleTimeString('id-ID') : '-'}</strong>
                        {loading ? ' · memuat...' : ''}
                    </div>

                    {errorText ? (
                        <div style={{ padding: '16px 18px', color: '#b91c1c', fontWeight: 600 }}>{errorText}</div>
                    ) : null}

                    {!meta.exists ? (
                        <CrudEmptyState title="File laravel.log belum ada" description="Buat traffic/error dulu atau cek konfigurasi logging environment." />
                    ) : entries.length === 0 ? (
                        <CrudEmptyState title="Tidak ada entry" description="Coba ubah filter level/keyword atau naikkan limit." />
                    ) : (
                        <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                            {entries.map((entry) => (
                                <li
                                    key={entry.id}
                                    style={{
                                        borderBottom: '1px solid var(--mhs-border-2, #e5e7eb)',
                                        padding: '16px 18px',
                                    }}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                        <span className={`mcr-dot-badge ${levelBadgeClass(entry.level)}`}>{entry.level.toUpperCase()}</span>
                                        <span className="mcr-dot-badge alumni">{entry.channel}</span>
                                        <span style={{ fontSize: '0.8rem', color: 'var(--mhs-text-muted, #64748b)' }}>{entry.timestamp}</span>
                                    </div>

                                    <p style={{ margin: 0, fontSize: '0.98rem', lineHeight: 1.5, fontWeight: 600 }}>{entry.message || '(no message)'}</p>

                                    <details style={{ marginTop: 10 }}>
                                        <summary style={{ cursor: 'pointer', fontSize: '0.82rem', fontWeight: 600, color: 'var(--mhs-primary, #2563eb)' }}>
                                            Lihat raw entry
                                        </summary>
                                        <pre style={{ marginTop: 8, fontSize: '0.76rem', whiteSpace: 'pre-wrap', overflowX: 'auto' }}>
                                            {entry.content}
                                        </pre>
                                    </details>
                                </li>
                            ))}
                        </ul>
                    )}
                </CrudCard>
            </div>
        </AppLayout>
    );
}
