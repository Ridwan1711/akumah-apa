type ActivityItem = {
    id: string;
    at: string;
    text: string;
};

type Props = {
    items: ActivityItem[];
};

export default function ActivityFeed({ items }: Props) {
    return (
        <div className="rounded-md border bg-muted/20 p-2">
            <div className="mb-2 text-xs font-semibold">Aktivitas realtime</div>
            {items.length === 0 ? (
                <div className="text-[11px] text-muted-foreground">Belum ada aktivitas.</div>
            ) : (
                <ul className="max-h-44 space-y-1 overflow-auto">
                    {items.map((item) => (
                        <li key={item.id} className="rounded border bg-background px-2 py-1 text-[11px]">
                            <div className="text-foreground">{item.text}</div>
                            <div className="text-[10px] text-muted-foreground">{item.at}</div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

