type StatCardProps = {
    icon: React.ElementType;
    label: string;
    value: number | string;
    color: string;
};

export function StatCard({ icon: Icon, label, value, color }: StatCardProps) {
    return (
        <div className={`flex items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm ${color}`}>
            <div className="rounded-lg bg-muted p-2">
                <Icon className="h-4 w-4 text-muted-foreground" />
            </div>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground truncate">{label}</p>
                <p className="text-xl font-bold leading-tight">{value}</p>
            </div>
        </div>
    );
}
