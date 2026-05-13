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
import { useQueueRunsPoll } from '@/hooks/use-queue-runs-poll';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { Auth, BreadcrumbItem } from '@/types';

import { ShellUserAvatar } from './shell-user-avatar';

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

    const { resolvedAppearance, updateAppearance } = useAppearance();

    const [userOpen, setUserOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [notifLoading, setNotifLoading] = useState(false);
    const [queueOpen, setQueueOpen] = useState(false);
    const [queueScope, setQueueScope] = useState<'my' | 'all'>('my');
    const [retryingRunId, setRetryingRunId] = useState<number | null>(null);

    const onQueueServerScope = useCallback((next: 'my' | 'all') => {
        setQueueScope(next);
    }, []);

    const {
        runs: queueRuns,
        activeCount: activeQueueCount,
        canViewAll: queueCanViewAll,
        queueLoading,
        refetch: refetchQueueRuns,
    } = useQueueRunsPoll({
        enabled: Boolean(user),
        scope: queueScope,
        panelOpen: queueOpen,
        onServerScope: onQueueServerScope,
    });

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
            const { data } = await axios.get<NotificationListResponse | NotificationItem[]>(
                '/notifications',
            );
            const items = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
            setNotifications(items);
        } catch {
            setNotifications([]);
        } finally {
            setNotifLoading(false);
        }
    }, []);

    useEffect(() => {
        if (notifOpen && user) {
            fetchNotifications();
        }
    }, [notifOpen, user, fetchNotifications]);

    useEffect(() => {
        function onClick(e: MouseEvent) {
            const target = e.target as Node;
            if (userOpen && userRef.current && !userRef.current.contains(target))
                setUserOpen(false);
            if (notifOpen && notifRef.current && !notifRef.current.contains(target))
                setNotifOpen(false);
            if (queueOpen && queueRef.current && !queueRef.current.contains(target))
                setQueueOpen(false);
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
                if (item.url) router.visit(item.url);
                else router.reload();
            });
    }

    function handleClearAll() {
        axios
            .post('/notifications/read-all')
            .then(() => {
                router.reload({ only: ['unreadNotificationsCount'] });
                setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
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
            await refetchQueueRuns({ silent: true });
        } catch {
            // noop
        } finally {
            setRetryingRunId(null);
        }
    }

    return (
        <header className="mhs-navbar" role="banner">
            {/* Toggle */}
            <button
                type="button"
                className="mhs-navbar-toggle"
                onClick={onToggleSidebar}
                aria-label="Toggle sidebar"
            >
                <Menu size={19} strokeWidth={2} />
            </button>

            {/* Search */}
            <div className="mhs-search-bar">
                <Search size={15} aria-hidden="true" />
                <input
                    type="text"
                    placeholder="Cari santri, ustadz, kelas…"
                    aria-label="Pencarian global"
                />
            </div>

            <div className="mhs-navbar-actions">
                {/* Theme toggle */}
                <button
                    type="button"
                    className="mhs-theme-toggle"
                    onClick={handleToggleTheme}
                    title={
                        resolvedAppearance === 'dark'
                            ? 'Aktifkan mode terang'
                            : 'Aktifkan mode gelap'
                    }
                    aria-label="Toggle tema"
                >
                    <span className="mhs-theme-toggle-thumb" aria-hidden="true">
                        {resolvedAppearance === 'dark' ? (
                            <Moon size={10} />
                        ) : (
                            <Sun size={10} />
                        )}
                    </span>
                </button>

                {/* ── Notification panel ── */}
                <div className="mhs-user-dropdown-wrap" ref={notifRef}>
                    <button
                        type="button"
                        className="mhs-icon-btn"
                        onClick={() => {
                            setUserOpen(false);
                            setQueueOpen(false);
                            setNotifOpen((v) => !v);
                        }}
                        aria-label={`Notifikasi${unread > 0 ? `, ${unread} belum dibaca` : ''}`}
                        aria-expanded={notifOpen}
                        aria-haspopup="menu"
                    >
                        <Bell size={16} />
                        {unread > 0 && (
                            <span className="mhs-notif-count-badge" aria-hidden="true">
                                {unread > 9 ? '9+' : unread}
                            </span>
                        )}
                    </button>

                    <div
                        className={`mhs-notif-panel${notifOpen ? ' mhs-open' : ''}`}
                        role="menu"
                        aria-label="Panel notifikasi"
                    >
                        <div className="mhs-notif-header">
                            <h4>
                                Notifikasi
                                {unread > 0 && (
                                    <span className="mhs-notif-count" aria-label={`${unread} belum dibaca`}>
                                        {unread > 9 ? '9+' : unread}
                                    </span>
                                )}
                            </h4>
                            {notifications.length > 0 && (
                                <button
                                    type="button"
                                    className="mhs-notif-clear"
                                    onClick={handleClearAll}
                                >
                                    <Check size={11} aria-hidden="true" />
                                    Tandai semua
                                </button>
                            )}
                        </div>

                        <div className="mhs-notif-list">
                            {notifLoading ? (
                                <div className="mhs-notif-loading">
                                    <Loader2 size={20} className="mhs-spin" aria-hidden="true" />
                                    <div>Memuat notifikasi…</div>
                                </div>
                            ) : notifications.length === 0 ? (
                                <div className="mhs-notif-empty">
                                    <span className="mhs-notif-empty-icon" aria-hidden="true">
                                        <Bell size={18} />
                                    </span>
                                    Tidak ada notifikasi baru
                                </div>
                            ) : (
                                notifications.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className="mhs-notif-item"
                                        onClick={() => handleNotifClick(item)}
                                        /* unread accent via CSS attr selector */
                                        data-unread={item.is_read === false ? 'true' : undefined}
                                        role="menuitem"
                                    >
                                        <span className="mhs-notif-icon" aria-hidden="true">
                                            <Bell size={15} />
                                        </span>
                                        <span className="mhs-notif-content">
                                            <span className="mhs-notif-title">{item.title}</span>
                                            <span className="mhs-notif-msg">
                                                {item.message || item.body || ''}
                                            </span>
                                            <span className="mhs-notif-time">
                                                {formatRelativeTime(item.created_at)}
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Queue panel ── */}
                <div className="mhs-user-dropdown-wrap" ref={queueRef}>
                    <button
                        type="button"
                        className="mhs-icon-btn"
                        onClick={() => {
                            setUserOpen(false);
                            setNotifOpen(false);
                            setQueueOpen((v) => !v);
                        }}
                        aria-label={`Aktivitas Queue${activeQueueCount > 0 ? `, ${activeQueueCount} aktif` : ''}`}
                        aria-expanded={queueOpen}
                        aria-haspopup="menu"
                    >
                        <Clock3 size={16} />
                        {activeQueueCount > 0 && (
                            <span className="mhs-notif-count-badge" aria-hidden="true">
                                {activeQueueCount > 9 ? '9+' : activeQueueCount}
                            </span>
                        )}
                    </button>

                    <div
                        className={`mhs-notif-panel${queueOpen ? ' mhs-open' : ''}`}
                        role="menu"
                        aria-label="Panel queue"
                    >
                        <div className="mhs-notif-header">
                            <h4>Aktivitas Queue</h4>
                            {queueCanViewAll && (
                                <div style={{ display: 'inline-flex', gap: 8 }}>
                                    {(['my', 'all'] as const).map((scope) => (
                                        <button
                                            key={scope}
                                            type="button"
                                            className="mhs-notif-clear"
                                            style={{
                                                opacity: queueScope === scope ? 1 : 0.55,
                                                textDecoration:
                                                    queueScope === scope ? 'underline' : 'none',
                                            }}
                                            onClick={() => setQueueScope(scope)}
                                        >
                                            {scope === 'my' ? 'My Runs' : 'All Runs'}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="mhs-notif-list">
                            {queueLoading ? (
                                <div className="mhs-notif-loading">
                                    <Loader2 size={20} className="mhs-spin" aria-hidden="true" />
                                    <div>Memuat proses queue…</div>
                                </div>
                            ) : queueRuns.length === 0 ? (
                                <div className="mhs-notif-empty">
                                    <span className="mhs-notif-empty-icon" aria-hidden="true">
                                        <Clock3 size={18} />
                                    </span>
                                    Belum ada proses queue
                                </div>
                            ) : (
                                queueRuns.map((run) => {
                                    const isActive =
                                        run.status !== 'failed' && run.progress_percent < 100;
                                    const isFailed = run.status === 'failed';

                                    return (
                                        <div
                                            key={run.id}
                                            className="mhs-notif-item"
                                            style={{ cursor: 'default' }}
                                            role="menuitem"
                                        >
                                            <span className="mhs-notif-icon" aria-hidden="true">
                                                <Clock3 size={15} />
                                            </span>
                                            <span className="mhs-notif-content">
                                                <span className="mhs-notif-title">
                                                    {run.title}
                                                </span>
                                                <span className="mhs-notif-msg">
                                                    {run.processed_rows}/{run.total_rows || '?'}{' '}
                                                    baris — {run.status}
                                                </span>
                                                {queueScope === 'all' && run.requested_by && (
                                                    <span className="mhs-notif-time">
                                                        By: {run.requested_by}
                                                    </span>
                                                )}
                                                <span className="mhs-notif-time">
                                                    {run.created_at || '–'}
                                                </span>

                                                {/* Progress bar with shimmer */}
                                                <span className="mhs-queue-progress-track">
                                                    <span
                                                        className="mhs-queue-progress-fill"
                                                        data-active={isActive ? 'true' : undefined}
                                                        data-failed={isFailed ? 'true' : undefined}
                                                        style={{
                                                            width: `${Math.max(0, Math.min(100, run.progress_percent))}%`,
                                                        }}
                                                    />
                                                </span>

                                                {run.error_message && (
                                                    <span
                                                        className="mhs-notif-time"
                                                        style={{ color: 'var(--mhs-danger)' }}
                                                    >
                                                        {run.error_message}
                                                    </span>
                                                )}
                                                {run.can_retry && (
                                                    <span
                                                        style={{
                                                            marginTop: 8,
                                                            display: 'inline-flex',
                                                        }}
                                                    >
                                                        <button
                                                            type="button"
                                                            className="mhs-notif-clear"
                                                            disabled={retryingRunId === run.id}
                                                            onClick={() =>
                                                                handleRetryQueueRun(run.id)
                                                            }
                                                            style={{
                                                                opacity:
                                                                    retryingRunId === run.id
                                                                        ? 0.6
                                                                        : 1,
                                                            }}
                                                        >
                                                            {retryingRunId === run.id
                                                                ? 'Retrying…'
                                                                : 'Retry'}
                                                        </button>
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>

                {/* ── User dropdown ── */}
                <div className="mhs-user-dropdown-wrap" ref={userRef}>
                    <button
                        type="button"
                        className="mhs-user-btn"
                        onClick={() => {
                            setNotifOpen(false);
                            setQueueOpen(false);
                            setUserOpen((v) => !v);
                        }}
                        aria-haspopup="menu"
                        aria-expanded={userOpen}
                        aria-label={user ? `Menu akun, ${user.name}` : 'Menu akun'}
                    >
                        {user ? (
                            <ShellUserAvatar user={user} variant="navbar" />
                        ) : (
                            <span className="mhs-user-btn-avatar" aria-hidden="true">
                                ?
                            </span>
                        )}
                        <span className="mhs-user-btn-name">
                            {user?.name?.split(' ').slice(0, 2).join(' ') ?? 'User'}
                        </span>
                        <ChevronDown size={13} aria-hidden="true" />
                    </button>

                    <div
                        className={`mhs-dropdown-menu${userOpen ? ' mhs-open' : ''}`}
                        role="menu"
                        aria-label="Menu akun"
                    >
                        <div className="mhs-dropdown-header">
                            <div className="mhs-dh-name">{user?.name ?? 'Pengguna'}</div>
                            <div className="mhs-dh-email">{user?.email ?? ''}</div>
                        </div>
                        <Link
                            className="mhs-dropdown-item"
                            href={editProfile()}
                            prefetch
                            onClick={() => setUserOpen(false)}
                            role="menuitem"
                        >
                            <UserCircle size={14} aria-hidden="true" />
                            Profil Saya
                        </Link>
                        <Link
                            className="mhs-dropdown-item"
                            href="/settings/profile"
                            prefetch
                            onClick={() => setUserOpen(false)}
                            role="menuitem"
                        >
                            <Settings size={14} aria-hidden="true" />
                            Pengaturan
                        </Link>
                        <button
                            type="button"
                            className="mhs-dropdown-item"
                            onClick={() => setUserOpen(false)}
                            role="menuitem"
                        >
                            <HelpCircle size={14} aria-hidden="true" />
                            Bantuan
                        </button>
                        <div className="mhs-dropdown-divider" />
                        <Link
                            href={logout()}
                            as="button"
                            className="mhs-dropdown-item mhs-danger"
                            onClick={handleLogout}
                            data-test="logout-button"
                            role="menuitem"
                        >
                            <LogOut size={14} aria-hidden="true" />
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
                        <span
                            key={`${bc.title}-${idx}`}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}
                        >
                            {idx > 0 && (
                                <span className="mhs-bc-sep" aria-hidden="true">›</span>
                            )}
                            {isLast ? (
                                <span className="mhs-bc-current" aria-current="page">
                                    {bc.title}
                                </span>
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