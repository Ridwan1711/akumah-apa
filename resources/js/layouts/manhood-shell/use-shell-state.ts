import { useCallback, useEffect, useState } from 'react';

const COLLAPSE_KEY = 'mhs:sidebar-collapsed';

function getInitialCollapsed(): boolean {
    if (typeof window === 'undefined') return false;
    try {
        return localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
}

export function useShellState() {
    const [collapsed, setCollapsed] = useState<boolean>(getInitialCollapsed);
    const [mobileOpen, setMobileOpen] = useState<boolean>(false);

    const toggleSidebar = useCallback(() => {
        if (typeof window !== 'undefined' && window.innerWidth <= 600) {
            setMobileOpen((v) => !v);
            return;
        }
        setCollapsed((v) => {
            const next = !v;
            try {
                localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0');
            } catch {
                /* ignore */
            }
            return next;
        });
    }, []);

    const closeMobile = useCallback(() => setMobileOpen(false), []);

    useEffect(() => {
        if (!mobileOpen) return;
        const onResize = () => {
            if (window.innerWidth > 600) setMobileOpen(false);
        };
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, [mobileOpen]);

    return { collapsed, mobileOpen, toggleSidebar, closeMobile };
}
