import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useMemo } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { toUrl } from '@/lib/utils';
import type { Auth } from '@/types';

import {
    buildSidebarSections,
    formatRoleLabel,
    getUserRoleNames,
} from './sidebar-build-sections';

type Props = {
    collapsed: boolean;
    mobileOpen: boolean;
    onClose: () => void;
};

export function ShellSidebar({ collapsed, mobileOpen, onClose }: Props) {
    const { auth, hasGuruRecord = false, hasWaliKelasRecord = false, hasKitabReadingExaminerRecord = false } =
        usePage<{
            auth: Auth;
            hasGuruRecord?: boolean;
            hasWaliKelasRecord?: boolean;
            hasKitabReadingExaminerRecord?: boolean;
        }>().props;

    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();

    const user = auth.user;
    const initials = getInitials(user.name ?? '');
    const userRoleNames = useMemo(() => getUserRoleNames(user), [user]);
    const sections = useMemo(
        () => buildSidebarSections(auth, userRoleNames, !!hasGuruRecord, !!hasWaliKelasRecord, !!hasKitabReadingExaminerRecord),
        [auth, userRoleNames, hasGuruRecord, hasWaliKelasRecord, hasKitabReadingExaminerRecord],
    );
    const roleLabel = useMemo(() => formatRoleLabel(userRoleNames), [userRoleNames]);

    const sidebarClass = ['mhs-sidebar', collapsed ? 'mhs-collapsed' : '', mobileOpen ? 'mhs-mobile-open' : '']
        .filter(Boolean)
        .join(' ');

    return (
        <aside className={sidebarClass} aria-label="Sidebar navigasi">
            <Link href="/dashboard" className="mhs-sidebar-logo" prefetch>
                <span className="mhs-logo-icon" aria-hidden="true">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                </span>
                <span className="mhs-logo-text">
                    <h2>Manhood Panel</h2>
                    <span>Pesantren Management</span>
                </span>
            </Link>

            <nav className="mhs-sidebar-nav" aria-label="Menu utama">
                {sections.map((section) => (
                    <div className="mhs-nav-section" key={section.label}>
                        <div className="mhs-nav-section-label">{section.label}</div>
                        {section.items.map((item) => {
                            const Icon = item.icon as LucideIcon | undefined;
                            const active =
                                item.href === '/dashboard' ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);
                            return (
                                <Link
                                    key={`${section.label}-${item.title}`}
                                    href={item.href}
                                    prefetch
                                    onClick={onClose}
                                    className={`mhs-nav-item${active ? ' mhs-active' : ''}`}
                                    title={item.title}
                                >
                                    {Icon ? (
                                        <Icon size={18} strokeWidth={2} aria-hidden="true" />
                                    ) : (
                                        <span style={{ width: 18, height: 18, display: 'inline-block' }} />
                                    )}
                                    <span className="mhs-nav-item-label">{item.title}</span>
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="mhs-sidebar-footer">
                <Link href={toUrl('/settings/profile')} className="mhs-user-card" prefetch>
                    <span className="mhs-user-avatar" aria-hidden="true">
                        {initials || 'US'}
                    </span>
                    <span className="mhs-user-info">
                        <span className="mhs-name">{user.name}</span>
                        <span className="mhs-role">{roleLabel}</span>
                    </span>
                </Link>
            </div>
        </aside>
    );
}
