import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'Platform',
    collapsible = false,
}: {
    items: NavItem[];
    label?: string;
    collapsible?: boolean;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const hasActiveItem = items.some((item) => isCurrentUrl(item.href));

    if (!collapsible) {
        return (
            <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/80">
                        {label}
                    </SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        );
    }

    return (
        <Collapsible defaultOpen={hasActiveItem} className="group/nav-collapsible">
            <SidebarGroup className="px-2 py-0">
                <div className="flex items-center justify-between px-2 py-1 group-data-[collapsible=icon]:hidden">
                    <SidebarGroupLabel className="h-auto p-0 text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/80">
                        {label}
                    </SidebarGroupLabel>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="rounded p-1 text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            aria-label={`Toggle ${label}`}
                        >
                            <ChevronRight className="size-4 transition-transform group-data-[state=open]/nav-collapsible:rotate-90" />
                        </button>
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
