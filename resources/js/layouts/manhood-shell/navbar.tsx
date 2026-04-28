import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Bell,
    Clock3,
    Check,
    ChevronDown,
    HelpCircle,
    Loader2,
    LogOut,
    Menu,
    Moon,
    Search,
    Settings,
    Sun,
    UserCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { Auth, BreadcrumbItem } from '@/types';

type NotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string;
    body?: string;
    url: string | null;
    created_at: string;
    is_read?: boolean;
};

type NotificationListResponse = {
    data: NotificationItem[];
    meta?: {
        current_page?: number;
        per_page?: number;
        total?: number;
        unread_count?: number;
    };
};

type QueueRunItem = {
    id: number;
    uuid: string;
    title: string;
    type: string;
    job_type: string | null;
    status: 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled';
    progress_percent: number;
    processed_rows: number;
    total_rows: number;
    error_message: string | null;
    created_at: string | null;
    requested_by?: string | null;
    can_retry?: boolean;
};

type QueueRunResponse = {
    data: QueueRunItem[];
    meta?: {
        active_count?: number;
        can_view_all?: boolean;
        current_scope?: 'my' | 'all';
    };
};

function formatRelativeTime(value: string | undefined): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const diffSec = Math.round((Date.now() - date.getTime()) / 1000);
    if (diffSec < 60) return 'Baru saja';
    const diffMin = Math.round(diffSec / 60);
    if (diffMin < 60) return `${diffMin} menit lalu`;
    const diffHour = Math.round(diffMin / 60);
    if (diffHour < 24) return `${diffHour} jam lalu`;
    const diffDay = Math.round(diffHour / 24);
    if (diffDay < 7) return `${diffDay} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

type Props = {
    onToggleSidebar: () => void;
};

export function ShellNavbar({ onToggleSidebar }: Props) {
    const { auth, unreadNotificationsCount } =
        usePage<{ auth: Auth; unreadNotificationsCount?: number }>().props;
    const user = auth?.user;
    const getInitials = useInitials();
    const initials = user?.name ? getInitials(user.name) : 'US';

    const { resolvedAppearance, updateAppearance } = useAppearance();

    const [userOpen, setUserOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [notifLoading, setNotifLoading] = useState(false);
    const [queueOpen, setQueueOpen] = useState(false);
    const [queueLoading, setQueueLoading] = useState(false);
    const [queueRuns, setQueueRuns] = useState<QueueRunItem[]>([]);
    const [activeQueueCount, setActiveQueueCount] = useState(0);
    const [queueScope, setQueueScope] = useState<'my' | 'all'>('my');
    const [queueCanViewAll, setQueueCanViewAll] = useState(false);
    const [retryingRunId, setRetryingRunId] = useState<number | null>(null);

    const userRef = useRef<HTMLDivElement | null>(null);
    const notifRef = useRef<HTMLDivElement | null>(null);
    const queueRef = useRef<HTMLDivElement | null>(null);

    const unread = unreadNotificationsCount ?? 0;

    const handleToggleTheme = useCallback(() => {
        updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark');
    }, [resolvedAppearance, updateAppearance]);

    const fetchNotifications = useCallback(async () => {
        setNotifLoading(true);
        try {
            const { data } = await axios.get<NotificationListResponse | NotificationItem[]>('/notifications');
            const items = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
            setNotifications(items);
        } catch {
            setNotifications([]);
        } finally {
            setNotifLoading(false);
        }
    }, []);

    const fetchQueueRuns = useCallback(async () => {
        setQueueLoading(true);
        try {
            const { data } = await axios.get<QueueRunResponse>('/queue-runs', {
                params: { scope: queueScope },
            });
            setQueueRuns(Array.isArray(data?.data) ? data.data : []);
            setActiveQueueCount(data?.meta?.active_count ?? 0);
            setQueueCanViewAll(Boolean(data?.meta?.can_view_all));
            if (data?.meta?.current_scope) {
                setQueueScope(data.meta.current_scope);
            }
        } catch {
            setQueueRuns([]);
            setActiveQueueCount(0);
        } finally {
            setQueueLoading(false);
        }
    }, [queueScope]);

    useEffect(() => {
        if (notifOpen && user) {
            fetchNotifications();
        }
    }, [notifOpen, user, fetchNotifications]);

    useEffect(() => {
        if (queueOpen && user) {
            fetchQueueRuns();
        }
    }, [queueOpen, user, fetchQueueRuns]);

    useEffect(() => {
        if (!user) return;
        fetchQueueRuns();
        const timer = window.setInterval(fetchQueueRuns, 15000);
        return () => window.clearInterval(timer);
    }, [user, fetchQueueRuns]);

    useEffect(() => {
        function onClick(e: MouseEvent) {
            const target = e.target as Node;
            if (userOpen && userRef.current && !userRef.current.contains(target)) {
                setUserOpen(false);
            }
            if (notifOpen && notifRef.current && !notifRef.current.contains(target)) {
                setNotifOpen(false);
            }
            if (queueOpen && queueRef.current && !queueRef.current.contains(target)) {
                setQueueOpen(false);
            }
        }
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') {
                setUserOpen(false);
                setNotifOpen(false);
                setQueueOpen(false);
            }
        }
        window.addEventListener('mousedown', onClick);
        window.addEventListener('keydown', onKey);
        return () => {
            window.removeEventListener('mousedown', onClick);
            window.removeEventListener('keydown', onKey);
        };
    }, [userOpen, notifOpen, queueOpen]);

    function handleNotifClick(item: NotificationItem) {
        axios
            .post(`/notifications/${item.id}/read`)
            .catch(() => undefined)
            .finally(() => {
                setNotifOpen(false);
                if (item.url) {
                    router.visit(item.url);
                } else {
                    router.reload();
                }
            });
    }

    function handleClearAll() {
        axios
            .post('/notifications/read-all')
            .then(() => {
                router.reload({ only: ['unreadNotificationsCount'] });
            })
            .catch(() => undefined);
    }

    function handleLogout() {
        router.flushAll();
    }

    async function handleRetryQueueRun(runId: number) {
        if (retryingRunId !== null) return;
        setRetryingRunId(runId);
        try {
            await axios.post(`/queue-runs/${runId}/retry`);
            await fetchQueueRuns();
        } catch {
            // noop
        } finally {
            setRetryingRunId(null);
        }
    }

    return (
        <header className="mhs-navbar" role="banner">
            <button
                type="button"
                className="mhs-navbar-toggle"
                onClick={onToggleSidebar}
                aria-label="Toggle sidebar"
            >
                <Menu size={20} strokeWidth={2} />
            </button>

            <div className="mhs-search-bar">
                <Search size={16} aria-hidden="true" />
                <input type="text" placeholder="Cari santri, ustadz, kelas..." />
            </div>

            <div className="mhs-navbar-actions">
                <button
                    type="button"
                    className="mhs-theme-toggle"
                    onClick={handleToggleTheme}
                    title={resolvedAppearance === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
                    aria-label="Toggle theme"
                >
                    <span className="mhs-theme-toggle-thumb" aria-hidden="true">
                        {resolvedAppearance === 'dark' ? <Moon size={11} /> : <Sun size={11} />}
                    </span>
                </button>

                <div className="mhs-user-dropdown-wrap" ref={notifRef}>
                    <button
                        type="button"
                        className="mhs-icon-btn"
                        onClick={() => {
                            setUserOpen(false);
                            setNotifOpen((v) => !v);
                        }}
                        aria-label="Notifikasi"
                        aria-expanded={notifOpen}
                    >
                        <Bell size={17} />
                        {unread > 0 ? (
                            <span className="mhs-notif-count-badge">{unread > 9 ? '9+' : unread}</span>
                        ) : null}
                    </button>
                    <div className={`mhs-notif-panel${notifOpen ? ' mhs-open' : ''}`} role="menu">
                        <div className="mhs-notif-header">
                            <h4>
                                Notifikasi
                                {unread > 0 ? <span className="mhs-notif-count">{unread > 9 ? '9+' : unread}</span> : null}
                            </h4>
                            {notifications.length > 0 ? (
                                <button type="button" className="mhs-notif-clear" onClick={handleClearAll}>
                                    <Check size={12} style={{ verticalAlign: 'middle', marginRight: 4 }} />
                                    Tandai semua
                                </button>
                            ) : null}
                        </div>
                        <div className="mhs-notif-list">
                            {notifLoading ? (
                                <div className="mhs-notif-loading">
                                    <Loader2 size={20} className="mhs-spin" />
                                    <div style={{ marginTop: 8 }}>Memuat notifikasi…</div>
                                </div>
                            ) : notifications.length === 0 ? (
                                <div className="mhs-notif-empty">Tidak ada notifikasi baru</div>
                            ) : (
                                notifications.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className="mhs-notif-item"
                                        onClick={() => handleNotifClick(item)}
                                    >
                                        <span className="mhs-notif-icon" aria-hidden="true">
                                            <Bell size={16} />
                                        </span>
                                        <span className="mhs-notif-content">
                                            <span className="mhs-notif-title">{item.title}</span>
                                            <span className="mhs-notif-msg">{item.message || item.body || ''}</span>
                                            <span className="mhs-notif-time">{formatRelativeTime(item.created_at)}</span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                <div className="mhs-user-dropdown-wrap" ref={queueRef}>
                    <button
                        type="button"
                        className="mhs-icon-btn"
                        onClick={() => {
                            setUserOpen(false);
                            setNotifOpen(false);
                            setQueueOpen((v) => !v);
                        }}
                        aria-label="Aktivitas Queue"
                        aria-expanded={queueOpen}
                    >
                        <Clock3 size={17} />
                        {activeQueueCount > 0 ? (
                            <span className="mhs-notif-count-badge">{activeQueueCount > 9 ? '9+' : activeQueueCount}</span>
                        ) : null}
                    </button>
                    <div className={`mhs-notif-panel${queueOpen ? ' mhs-open' : ''}`} role="menu">
                        <div className="mhs-notif-header">
                            <h4>Aktivitas Queue</h4>
                            {queueCanViewAll ? (
                                <div style={{ display: 'inline-flex', gap: 6 }}>
                                    <button
                                        type="button"
                                        className="mhs-notif-clear"
                                        style={{
                                            opacity: queueScope === 'my' ? 1 : 0.65,
                                            textDecoration: queueScope === 'my' ? 'underline' : 'none',
                                        }}
                                        onClick={() => setQueueScope('my')}
                                    >
                                        My Runs
                                    </button>
                                    <button
                                        type="button"
                                        className="mhs-notif-clear"
                                        style={{
                                            opacity: queueScope === 'all' ? 1 : 0.65,
                                            textDecoration: queueScope === 'all' ? 'underline' : 'none',
                                        }}
                                        onClick={() => setQueueScope('all')}
                                    >
                                        All Runs
                                    </button>
                                </div>
                            ) : null}
                        </div>
                        <div className="mhs-notif-list">
                            {queueLoading ? (
                                <div className="mhs-notif-loading">
                                    <Loader2 size={20} className="mhs-spin" />
                                    <div style={{ marginTop: 8 }}>Memuat proses queue…</div>
                                </div>
                            ) : queueRuns.length === 0 ? (
                                <div className="mhs-notif-empty">Belum ada proses queue</div>
                            ) : (
                                queueRuns.map((run) => (
                                    <div key={run.id} className="mhs-notif-item" style={{ cursor: 'default' }}>
                                        <span className="mhs-notif-icon" aria-hidden="true">
                                            <Clock3 size={16} />
                                        </span>
                                        <span className="mhs-notif-content">
                                            <span className="mhs-notif-title">{run.title}</span>
                                            <span className="mhs-notif-msg">
                                                {run.processed_rows}/{run.total_rows || '?'} baris - {run.status}
                                            </span>
                                            {queueScope === 'all' && run.requested_by ? (
                                                <span className="mhs-notif-time">By: {run.requested_by}</span>
                                            ) : null}
                                            <span className="mhs-notif-time">{run.created_at || '-'}</span>
                                            <span style={{ marginTop: 6, display: 'block', height: 5, borderRadius: 999, background: 'var(--mhs-bg-2)' }}>
                                                <span
                                                    style={{
                                                        display: 'block',
                                                        height: '100%',
                                                        width: `${Math.max(0, Math.min(100, run.progress_percent))}%`,
                                                        borderRadius: 999,
                                                        background: run.status === 'failed' ? '#ef4444' : 'var(--mhs-accent)',
                                                    }}
                                                />
                                            </span>
                                            {run.error_message ? (
                                                <span className="mhs-notif-time" style={{ color: '#ef4444' }}>
                                                    {run.error_message}
                                                </span>
                                            ) : null}
                                            {run.can_retry ? (
                                                <span style={{ marginTop: 8, display: 'inline-flex' }}>
                                                    <button
                                                        type="button"
                                                        className="mhs-notif-clear"
                                                        disabled={retryingRunId === run.id}
                                                        onClick={() => handleRetryQueueRun(run.id)}
                                                    >
                                                        {retryingRunId === run.id ? 'Retrying...' : 'Retry'}
                                                    </button>
                                                </span>
                                            ) : null}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                <div className="mhs-user-dropdown-wrap" ref={userRef}>
                    <button
                        type="button"
                        className="mhs-user-btn"
                        onClick={() => {
                            setNotifOpen(false);
                            setUserOpen((v) => !v);
                        }}
                        aria-haspopup="menu"
                        aria-expanded={userOpen}
                    >
                        <span className="mhs-user-btn-avatar" aria-hidden="true">
                            {initials}
                        </span>
                        <span className="mhs-user-btn-name">{user?.name?.split(' ').slice(0, 2).join(' ') ?? 'User'}</span>
                        <ChevronDown size={14} />
                    </button>
                    <div className={`mhs-dropdown-menu${userOpen ? ' mhs-open' : ''}`} role="menu">
                        <div className="mhs-dropdown-header">
                            <div className="mhs-dh-name">{user?.name ?? 'Pengguna'}</div>
                            <div className="mhs-dh-email">{user?.email ?? ''}</div>
                        </div>
                        <Link
                            className="mhs-dropdown-item"
                            href={editProfile()}
                            prefetch
                            onClick={() => setUserOpen(false)}
                        >
                            <UserCircle size={15} />
                            Profil Saya
                        </Link>
                        <Link
                            className="mhs-dropdown-item"
                            href="/settings/profile"
                            prefetch
                            onClick={() => setUserOpen(false)}
                        >
                            <Settings size={15} />
                            Pengaturan
                        </Link>
                        <button
                            type="button"
                            className="mhs-dropdown-item"
                            onClick={() => setUserOpen(false)}
                        >
                            <HelpCircle size={15} />
                            Bantuan
                        </button>
                        <div className="mhs-dropdown-divider" />
                        <Link
                            href={logout()}
                            as="button"
                            className="mhs-dropdown-item mhs-danger"
                            onClick={handleLogout}
                            data-test="logout-button"
                        >
                            <LogOut size={15} />
                            Keluar
                        </Link>
                    </div>
                </div>
            </div>
        </header>
    );
}

export function ShellBreadcrumbs({ breadcrumbs }: { breadcrumbs?: BreadcrumbItem[] }) {
    if (!breadcrumbs || breadcrumbs.length === 0) return null;
    return (
        <div className="mhs-page-header">
            <nav className="mhs-breadcrumb" aria-label="Breadcrumb">
                {breadcrumbs.map((bc, idx) => {
                    const isLast = idx === breadcrumbs.length - 1;
                    return (
                        <span key={`${bc.title}-${idx}`} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                            {idx > 0 ? <span className="mhs-bc-sep">›</span> : null}
                            {isLast ? (
                                <span className="mhs-bc-current">{bc.title}</span>
                            ) : (
                                <Link href={bc.href} prefetch>
                                    {bc.title}
                                </Link>
                            )}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
