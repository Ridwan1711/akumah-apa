import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
};

/** Scroll + border wrapper for tables (Manhood / contoh.md style). */
export function ManhoodDataTableShell({ children, className }: Props) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border bg-card shadow-sm dark:shadow-none',
                className,
            )}
        >
            <div className="overflow-x-auto">{children}</div>
        </div>
    );
}
