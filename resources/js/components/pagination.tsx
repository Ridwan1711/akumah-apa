import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

type Props = {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export default function Pagination({ links, from, to, total }: Props) {
    if (links.length <= 3) return null;

    return (
        <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">
                Menampilkan {from ?? 0} - {to ?? 0} dari {total} data
            </p>
            <div className="flex items-center gap-1">
                {links.map((link, i) => (
                    <Button
                        key={i}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        disabled={!link.url}
                        asChild={!!link.url}
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                preserveState
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
