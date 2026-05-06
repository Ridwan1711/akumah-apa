type Props = {
    icon?: React.ElementType;
    label: string;
    value: React.ReactNode;
    mono?: boolean;
};

export function ProfileInfoRow({ icon: Icon, label, value, mono }: Props) {
    return (
        <div className="group flex flex-col gap-1 py-3.5 transition-colors sm:flex-row sm:items-center sm:gap-4">
            <div className="flex items-center gap-3 sm:w-52 sm:shrink-0">
                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:bg-emerald-50 group-hover:text-emerald-600">
                    {Icon ? (
                        <Icon size={16} strokeWidth={2} />
                    ) : (
                        <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
                    )}
                </div>
                <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    {label}
                </span>
            </div>
            <div className={`text-sm font-medium text-foreground sm:flex-1 ${mono ? 'font-mono tracking-wider' : ''}`}>
                {value ?? <span className="font-normal text-muted-foreground italic">Belum ada data</span>}
            </div>
        </div>
    );
}
