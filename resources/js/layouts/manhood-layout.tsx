import { ManhoodShell } from '@/layouts/manhood-shell';
import type { AppLayoutProps } from '@/types';

/**
 * Global Manhood layout shell.
 *
 * Replaces the old shadcn/Tailwind-based AppShell with a fully custom
 * layout built from scratch — see `@/layouts/manhood-shell` and
 * `resources/css/manhood-shell.css` for the implementation.
 */
export default function ManhoodLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return <ManhoodShell breadcrumbs={breadcrumbs}>{children}</ManhoodShell>;
}
