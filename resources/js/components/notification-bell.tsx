import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Bell, Check, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

type NotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string;
    url: string | null;
    created_at: string;
};

export function NotificationBell() {
    const { auth } = usePage<{ auth: { user: { id: number } }; unreadNotificationsCount: number }>().props;
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const pageProps = usePage().props as { unreadNotificationsCount?: number };
    const count = pageProps?.unreadNotificationsCount ?? 0;

    const fetchNotifications = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await axios.get<NotificationItem[]>('/notifications');
            setNotifications(data);
        } catch {
            setNotifications([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (open && auth?.user) {
            fetchNotifications();
        }
    }, [open, auth?.user, fetchNotifications]);

    function handleMarkAsRead(id: string, url: string | null) {
        axios.post(`/notifications/${id}/read`).then(() => {
            setOpen(false);
            if (url) {
                router.visit(url);
            } else {
                router.reload();
            }
        });
    }

    function handleMarkAllAsRead() {
        axios.post('/notifications/read-all').then(() => {
            setOpen(false);
            router.reload({ only: ['unreadNotificationsCount'] });
        });
    }

    if (!auth?.user) return null;

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="size-5" />
                    {count > 0 && (
                        <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground">
                            {count > 9 ? '9+' : count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="font-semibold">Notifikasi</span>
                    {notifications.length > 0 && (
                        <Button variant="ghost" size="sm" onClick={handleMarkAllAsRead}>
                            <Check className="mr-1 size-3" />
                            Tandai Semua Dibaca
                        </Button>
                    )}
                </div>
                <div className="max-h-72 overflow-y-auto">
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="size-8 animate-spin text-muted-foreground" />
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="py-12 text-center text-sm text-muted-foreground">
                            Tidak ada notifikasi baru
                        </div>
                    ) : (
                        <div className="divide-y">
                            {notifications.map((n) => (
                                <button
                                    key={n.id}
                                    type="button"
                                    className={cn(
                                        'flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm transition-colors hover:bg-muted/50',
                                        !n.url && 'cursor-default'
                                    )}
                                    onClick={() => handleMarkAsRead(n.id, n.url)}
                                >
                                    <span className="font-medium">{n.title}</span>
                                    <span className="text-muted-foreground line-clamp-2">{n.message}</span>
                                    <span className="text-xs text-muted-foreground">{n.created_at}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
