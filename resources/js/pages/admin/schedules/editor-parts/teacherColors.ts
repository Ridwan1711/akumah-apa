/**
 * Palette warna untuk membedakan guru saat compare mode (multi-select).
 * Indeks dipakai berdasar urutan pemilihan, bukan teacher_id, supaya guru pertama
 * selalu warna A, kedua B, dst — gampang dibaca.
 */
export type TeacherColor = {
    /** Tailwind ring class untuk outline cell. */
    ring: string;
    /** Tailwind background untuk badge / dot. */
    badge: string;
    /** Tailwind text class untuk badge. */
    badgeText: string;
    /** Hex untuk inline use (tooltip dot, dll). */
    hex: string;
};

const PALETTE: TeacherColor[] = [
    { ring: 'ring-sky-500', badge: 'bg-sky-500', badgeText: 'text-white', hex: '#0ea5e9' },
    { ring: 'ring-emerald-500', badge: 'bg-emerald-500', badgeText: 'text-white', hex: '#10b981' },
    { ring: 'ring-amber-500', badge: 'bg-amber-500', badgeText: 'text-white', hex: '#f59e0b' },
    { ring: 'ring-rose-500', badge: 'bg-rose-500', badgeText: 'text-white', hex: '#f43f5e' },
    { ring: 'ring-violet-500', badge: 'bg-violet-500', badgeText: 'text-white', hex: '#8b5cf6' },
    { ring: 'ring-fuchsia-500', badge: 'bg-fuchsia-500', badgeText: 'text-white', hex: '#d946ef' },
    { ring: 'ring-teal-500', badge: 'bg-teal-500', badgeText: 'text-white', hex: '#14b8a6' },
    { ring: 'ring-indigo-500', badge: 'bg-indigo-500', badgeText: 'text-white', hex: '#6366f1' },
];

export function colorForTeacherIndex(index: number): TeacherColor {
    const i = ((index % PALETTE.length) + PALETTE.length) % PALETTE.length;
    return PALETTE[i];
}

/** Inisial 1-2 karakter dari nama (untuk badge). */
export function teacherInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}
