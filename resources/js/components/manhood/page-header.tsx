import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    actions?: React.ReactNode;
    className?: string;
};

export function ManhoodPageHeader({ title, description, actions, className }: Props) {
    return (
        <div className={cn('mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">{title}</h1>
                {description ? <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{description}</p> : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
