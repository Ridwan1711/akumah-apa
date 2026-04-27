import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AlertTriangle, Trash2, X } from 'lucide-react';

type StatTone = 'blue' | 'green' | 'amber' | 'purple';

export type CrudStatItem = {
    key: string;
    label: string;
    value: string | number;
    icon: ReactNode;
    tone: StatTone;
};

export function CrudPageHeader({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <section className="mcr-page-head">
            <h1>{title}</h1>
            <p>{description}</p>
        </section>
    );
}

export function CrudStatStrip({ items }: { items: CrudStatItem[] }) {
    return (
        <section className="mcr-stat-strip" aria-label="Ringkasan data">
            {items.map((item) => (
                <article key={item.key} className={`mcr-strip-card ${item.tone}`}>
                    <div className={`mcr-strip-icon ${item.tone}`}>{item.icon}</div>
                    <div className="mcr-strip-info">
                        <div className="mcr-val">{item.value}</div>
                        <div className="mcr-lbl">{item.label}</div>
                    </div>
                </article>
            ))}
        </section>
    );
}

export function CrudToolbar({
    left,
    right,
}: {
    left: ReactNode;
    right?: ReactNode;
}) {
    return (
        <section className="mcr-toolbar">
            <div className="mcr-toolbar-left">{left}</div>
            {right ? <div className="mcr-toolbar-right">{right}</div> : null}
        </section>
    );
}

export function CrudCard({
    title,
    subtitle,
    right,
    children,
}: {
    title?: string;
    subtitle?: string;
    right?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="mcr-card">
            {(title || subtitle || right) && (
                <header className="mcr-card-head">
                    <div>
                        {title ? <h3>{title}</h3> : null}
                        {subtitle ? <p>{subtitle}</p> : null}
                    </div>
                    {right ? <div>{right}</div> : null}
                </header>
            )}
            <div className="mcr-card-body">{children}</div>
        </section>
    );
}

export function CrudTableShell({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <section className="mcr-table-shell">
            <div className="mcr-table-wrap">{children}</div>
        </section>
    );
}

export function CrudEmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="mcr-empty-state">
            <h3>{title}</h3>
            <p>{description}</p>
        </div>
    );
}

export function CrudBulkActionBar({
    visible,
    selectedCount,
    onClear,
    children,
}: {
    visible: boolean;
    selectedCount: number;
    onClear: () => void;
    children: ReactNode;
}) {
    return (
        <div className={`mcr-bulk-bar${visible ? ' visible' : ''}`}>
            <span className="mcr-bcount">{selectedCount} data dipilih</span>
            <div className="mcr-bactions">{children}</div>
            <button type="button" className="mcr-bclose" onClick={onClear} aria-label="Tutup aksi massal">
                <X size={14} />
            </button>
        </div>
    );
}

export function CrudModal({
    open,
    title,
    subtitle,
    onClose,
    children,
    footer,
    wide = false,
}: {
    open: boolean;
    title: string;
    subtitle?: string;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    wide?: boolean;
}) {
    if (!open) return null;

    return (
        <div className="mcr-modal-overlay" role="dialog" aria-modal="true">
            <div className={`mcr-modal${wide ? ' wide' : ''}`}>
                <header className="mcr-modal-head">
                    <div>
                        <h3>{title}</h3>
                        {subtitle ? <p>{subtitle}</p> : null}
                    </div>
                    <button type="button" className="mcr-modal-close" onClick={onClose} aria-label="Close">
                        <X size={15} />
                    </button>
                </header>
                <div className="mcr-modal-body">{children}</div>
                {footer ? <footer className="mcr-modal-foot">{footer}</footer> : null}
            </div>
        </div>
    );
}

export function CrudConfirmModal({
    open,
    onClose,
    onConfirm,
    title = 'Konfirmasi Hapus',
    description,
    warningText = 'Tindakan ini tidak dapat dibatalkan.',
    confirmLabel = 'Hapus Sekarang',
    cancelLabel = 'Batal',
    loading = false,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title?: string;
    description: string;
    warningText?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    loading?: boolean;
}) {
    return (
        <CrudModal open={open} onClose={onClose} title={title}>
            <div className="mcr-confirm-wrap">
                <div className="mcr-confirm-icon">
                    <Trash2 size={16} />
                </div>
                <h4>{description}</h4>
                <p>{warningText}</p>
                <div className="mcr-confirm-note">
                    <AlertTriangle size={13} />
                    <span>Tindakan ini tidak dapat ditarik kembali.</span>
                </div>
                <div className="mcr-confirm-actions">
                    <button type="button" className="mcr-btn ghost" onClick={onClose} disabled={loading}>
                        {cancelLabel}
                    </button>
                    <button type="button" className="mcr-btn danger" onClick={onConfirm} disabled={loading}>
                        <Trash2 size={14} />
                        {loading ? 'Memproses...' : confirmLabel}
                    </button>
                </div>
            </div>
        </CrudModal>
    );
}

export function CrudPagination({
    links,
}: {
    links: Array<{ url: string | null; label: string; active: boolean }>;
}) {
    const normalized = links
        .map((link) => ({
            ...link,
            label: link.label
                .replace('&laquo;', '«')
                .replace('&raquo;', '»')
                .replace(/<[^>]*>/g, '')
                .trim(),
        }))
        .filter((link) => link.label.length > 0);

    if (normalized.length <= 1) return null;

    return (
        <nav className="mcr-pagination" aria-label="Pagination">
            {normalized.map((link, idx) => {
                if (!link.url) {
                    return (
                        <span key={`${link.label}-${idx}`} className="mcr-page-btn disabled">
                            {link.label}
                        </span>
                    );
                }
                return (
                    <Link
                        key={`${link.label}-${idx}`}
                        href={link.url}
                        className={`mcr-page-btn${link.active ? ' active' : ''}`}
                    >
                        {link.label}
                    </Link>
                );
            })}
        </nav>
    );
}

export function openDownload(url: string): void {
    window.open(url, '_blank', 'noopener,noreferrer');
}

export function visitWithPreserve(url: string, data: unknown) {
    router.get(url, data as never, { preserveState: true, preserveScroll: true });
}
