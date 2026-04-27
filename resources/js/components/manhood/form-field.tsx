import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
    className?: string;
};

export function ManhoodFormField({ id, label, hint, error, required, children, className }: Props) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id} className="text-xs font-medium text-muted-foreground">
                {label}
                {required ? <span className="text-destructive"> *</span> : null}
            </Label>
            {children}
            {hint && !error ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
            {error ? <p className="text-xs font-medium text-destructive">{error}</p> : null}
        </div>
    );
}
