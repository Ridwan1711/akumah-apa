import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    action?: ReactNode;
    children: ReactNode;
    emptyText?: string;
    isEmpty?: boolean;
    emptyIcon?: ReactNode;
    className?: string;
};

export function ManhoodSectionCard({
    title,
    action,
    children,
    emptyText,
    isEmpty = false,
    emptyIcon,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm dark:shadow-none',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-3 border-b border-border/80 bg-muted/30 px-5 py-4">
                <h2 className="text-base font-bold text-foreground">{title}</h2>
                {action}
            </div>
            <div className="p-5">
                {isEmpty ? (
                    <div className="flex flex-col items-center justify-center py-8 text-center">
                        {emptyIcon ? (
                            <div className="mb-2 rounded-full border border-border bg-muted/50 p-3">{emptyIcon}</div>
                        ) : null}
                        {emptyText ? <p className="text-sm text-muted-foreground">{emptyText}</p> : null}
                    </div>
                ) : (
                    children
                )}
            </div>
        </div>
    );
}
