import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types';
import { ShellBreadcrumbs, ShellNavbar } from './navbar';
import { ShellSidebar } from './sidebar';
import { useShellState } from './use-shell-state';

type Props = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

/**
 * ManhoodShell — Custom layout (sidebar + navbar) for the Manhood Panel.
 *
 * Intentionally built from raw HTML with `mhs-*` class names backed by
 * `resources/css/manhood-shell.css`. Does NOT depend on shadcn/ui, AppShell,
 * or Tailwind utilities — so shell styling cannot be overridden by page bodies.
 */
export function ManhoodShell({ children, breadcrumbs = [] }: Props) {
    const { collapsed, mobileOpen, toggleSidebar, closeMobile } = useShellState();

    return (
        <div className="mhs-app">
            <ShellSidebar
                collapsed={collapsed}
                mobileOpen={mobileOpen}
                onClose={closeMobile}
            />

            {/* Mobile overlay — rendered as a button for a11y */}
            {mobileOpen && (
                <button
                    type="button"
                    aria-label="Tutup sidebar"
                    className="mhs-sidebar-overlay mhs-visible"
                    onClick={closeMobile}
                    style={{ border: 'none', padding: 0 }}
                />
            )}

            <div className={`mhs-main${collapsed ? ' mhs-sidebar-collapsed' : ''}`}>
                <ShellNavbar onToggleSidebar={toggleSidebar} />
                <ShellBreadcrumbs breadcrumbs={breadcrumbs} />
                <main className="mhs-content" id="main-content" role="main" tabIndex={-1}>
                    {children}
                </main>
            </div>
        </div>
    );
}

export default ManhoodShell;