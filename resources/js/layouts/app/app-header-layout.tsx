import { ManhoodShell } from '@/layouts/manhood-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return <ManhoodShell breadcrumbs={breadcrumbs}>{children}</ManhoodShell>;
}
