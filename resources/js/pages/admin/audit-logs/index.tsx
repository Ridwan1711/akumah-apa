import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronDown, FileSearch, Filter, Shield, User } from 'lucide-react';
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
import type { AuditLog, BreadcrumbItem, PaginatedData } from '@/types';

type Props = {
    logs: PaginatedData<AuditLog>;
    modules: string[];
    users: { id: number; name: string }[];
    scopeDescription: string;
    filters: {
        module?: string;
        user_id?: string;
        action?: string;
        date_from?: string;
        date_to?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Log Aktivitas', href: '/admin/audit-logs' },
];

function fallbackSummary(log: AuditLog): string {
    const mod = log.module.replace(/_/g, ' ');
    if (log.action === 'create') return `${mod}: data baru`;
    if (log.action === 'update') return `${mod}: pembaruan data`;
    if (log.action === 'delete') return `${mod}: data dihapus`;

    return `${mod} (${log.action})`;
}

export default function AuditLogIndex({ logs, modules, users, filters, scopeDescription }: Props) {
    function applyFilters(next: Partial<Props['filters']>) {
        router.get('/admin/audit-logs', { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    }

    const activeFilters = [filters.module, filters.user_id, filters.action, filters.date_from, filters.date_to].filter(Boolean).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Log Aktivitas" />
            <div>
                <CrudPageHeader
                    title="Log Aktivitas"
                    description={`${scopeDescription} Ringkasan ditulis agar mudah dipahami; detail teknis tetap bisa dibuka per baris.`}
                />
                <CrudStatStrip
                    items={[
                        { key: 'total', label: 'Total Log', value: logs.total, icon: <FileSearch size={18} />, tone: 'blue' },
                        { key: 'module', label: 'Total Modul', value: modules.length, icon: <Shield size={18} />, tone: 'green' },
                        { key: 'user', label: 'Total User', value: users.length, icon: <User size={18} />, tone: 'amber' },
                        { key: 'filter', label: 'Filter Aktif', value: activeFilters, icon: <Filter size={18} />, tone: 'purple' },
                    ]}
                />

                <FlashMessage />
                <CrudToolbar
                    left={(
                        <>
                            <select className="mcr-filter-select" value={filters.module ?? 'all'} onChange={(e) => applyFilters({ module: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua Modul</option>
                                {modules.map((module) => (
                                    <option key={module} value={module}>{module}</option>
                                ))}
                            </select>
                            <select className="mcr-filter-select" value={filters.user_id ?? 'all'} onChange={(e) => applyFilters({ user_id: e.target.value === 'all' ? undefined : e.target.value })}>
                                <option value="all">Semua User</option>
                                {users.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.name}</option>
                                ))}
                            </select>
                            <input className="mcr-input" placeholder="Aksi (create/update/delete)" value={filters.action ?? ''} onChange={(e) => applyFilters({ action: e.target.value || undefined })} />
                            <input type="date" className="mcr-input" value={filters.date_from ?? ''} onChange={(e) => applyFilters({ date_from: e.target.value || undefined })} />
                            <input type="date" className="mcr-input" value={filters.date_to ?? ''} onChange={(e) => applyFilters({ date_to: e.target.value || undefined })} />
                            <button type="button" className="mcr-btn ghost" onClick={() => router.get('/admin/audit-logs', {}, { preserveState: true, preserveScroll: true })}>
                                Reset
                            </button>
                        </>
                    )}
                />

                <CrudCard>
                    {logs.data.length === 0 ? (
                        <CrudEmptyState title="Tidak ada log" description="Belum ada aktivitas sesuai filter saat ini." />
                    ) : (
                        <ul className="mcr-audit-feed" style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                            {logs.data.map((log) => {
                                const title = log.summary_line ?? fallbackSummary(log);
                                const when = log.time_relative ?? new Date(log.created_at).toLocaleString('id-ID');
                                const actor = log.actor_label ?? '';

                                return (
                                    <li
                                        key={log.id}
                                        className="mcr-audit-feed__item"
                                        style={{
                                            borderBottom: '1px solid var(--mhs-border-2, #e5e7eb)',
                                            padding: '16px 18px',
                                        }}
                                    >
                                        <p style={{ margin: 0, fontSize: '1.02rem', fontWeight: 600, lineHeight: 1.45, color: 'var(--mhs-text, inherit)' }}>
                                            {title}
                                        </p>
                                        <p style={{ margin: '8px 0 0', fontSize: '0.875rem', color: 'var(--mhs-text-muted, #64748b)' }}>
                                            <span style={{ fontWeight: 500 }}>{when}</span>
                                            {actor ? (
                                                <>
                                                    <span aria-hidden="true" style={{ margin: '0 6px', opacity: 0.5 }}>·</span>
                                                    <span>{actor}</span>
                                                </>
                                            ) : null}
                                        </p>
                                        <details style={{ marginTop: 10 }}>
                                            <summary
                                                style={{
                                                    cursor: 'pointer',
                                                    fontSize: '0.8rem',
                                                    fontWeight: 600,
                                                    color: 'var(--mhs-primary, #2563eb)',
                                                    listStyle: 'none',
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 4,
                                                }}
                                            >
                                                <ChevronDown size={14} style={{ flexShrink: 0 }} />
                                                Detail teknis
                                            </summary>
                                            <div style={{ marginTop: 10, fontSize: '0.8rem', color: 'var(--mhs-text-muted, #64748b)', lineHeight: 1.6 }}>
                                                <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                                                    <CalendarDays size={13} />
                                                    <span>{new Date(log.created_at).toLocaleString('id-ID')}</span>
                                                </div>
                                                <div>
                                                    <strong style={{ color: 'var(--mhs-text, inherit)' }}>Target:</strong>
                                                    {' '}
                                                    {log.technical_target ?? `${log.auditable_type} #${log.auditable_id}`}
                                                </div>
                                                <div>
                                                    <strong style={{ color: 'var(--mhs-text, inherit)' }}>Modul / aksi:</strong>
                                                    {' '}
                                                    {log.module}
                                                    {' '}
                                                    <span className="mcr-dot-badge alumni" style={{ verticalAlign: 'middle' }}>{log.action}</span>
                                                </div>
                                                <div>
                                                    <strong style={{ color: 'var(--mhs-text, inherit)' }}>User akun:</strong>
                                                    {' '}
                                                    {log.user?.name ?? '—'}
                                                </div>
                                                <div>
                                                    <strong style={{ color: 'var(--mhs-text, inherit)' }}>IP:</strong>
                                                    {' '}
                                                    {log.ip_address ?? '—'}
                                                </div>
                                            </div>
                                        </details>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <CrudPagination links={logs.links} />
                </CrudCard>
            </div>
        </AppLayout>
    );
}
