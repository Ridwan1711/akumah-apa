type Props = {
    icon: React.ElementType;
    title: string;
    subtitle?: string;
    action?: React.ReactNode;
    children: React.ReactNode;
};

export function ProfileSectionCard({ icon: Icon, title, subtitle, action, children }: Props) {
    return (
        <div className="overflow-hidden rounded-2xl bg-white border border-border shadow-sm transition-all hover:shadow-md">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/70 bg-muted/50 px-6 py-5">
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm border border-border text-foreground/90">
                        <Icon size={20} strokeWidth={1.5} />
                    </div>
                    <div>
                        <h3 className="text-2xl font-bold leading-none text-foreground">{title}</h3>
                        {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
                    </div>
                </div>
                {action}
            </div>
            <div className="divide-y divide-border/70 px-6">{children}</div>
        </div>
    );
}
