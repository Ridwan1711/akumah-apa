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
 * This shell is intentionally built from raw HTML elements with `mhs-*`
 * class names backed by `resources/css/manhood-shell.css`. It does NOT
 * depend on any shadcn/ui primitive, the AppShell/AppSidebar components,
 * or Tailwind utility classes — so its styling cannot be overridden by
 * Tailwind utilities used inside individual page bodies.
 */
export function ManhoodShell({ children, breadcrumbs = [] }: Props) {
    const { collapsed, mobileOpen, toggleSidebar, closeMobile } = useShellState();

    return (
        <div className="mhs-app">
            <ShellSidebar collapsed={collapsed} mobileOpen={mobileOpen} onClose={closeMobile} />

            {mobileOpen ? (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    className="mhs-sidebar-overlay mhs-visible"
                    onClick={closeMobile}
                    style={{ border: 'none', padding: 0 }}
                />
            ) : null}

            <div className={`mhs-main${collapsed ? ' mhs-sidebar-collapsed' : ''}`}>
                <ShellNavbar onToggleSidebar={toggleSidebar} />
                <ShellBreadcrumbs breadcrumbs={breadcrumbs} />
                <main className="mhs-content" role="main">
                    {children}
                </main>
            </div>
        </div>
    );
}

export default ManhoodShell;
