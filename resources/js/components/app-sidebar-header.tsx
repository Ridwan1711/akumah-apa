import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-[60px] shrink-0 items-center gap-3 border-b border-border bg-card px-4 shadow-[0_1px_3px_rgb(0_0_0_/6%),0_4px_16px_rgb(0_0_0_/4%)] transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6 dark:shadow-[0_1px_4px_rgb(0_0_0_/30%),0_4px_20px_rgb(0_0_0_/20%)]">
            <div className="flex flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <NotificationBell />
        </header>
    );
}
