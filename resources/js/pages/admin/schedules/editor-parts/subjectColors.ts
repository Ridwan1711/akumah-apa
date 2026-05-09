/**
 * Palette warna untuk cell mapel di matrix editor.
 * Bertujuan: subtle (tidak terlalu mencolok), kontras tetap baik dengan teks gelap & terang.
 * Setiap entri memberikan kelas Tailwind background + text agar konsisten light/dark mode.
 */
export type SubjectColor = {
    bg: string;
    border: string;
    dot: string;
};

const PALETTE: SubjectColor[] = [
    { bg: 'bg-sky-100 dark:bg-sky-900/40', border: 'border-sky-300 dark:border-sky-700', dot: 'bg-sky-500' },
    { bg: 'bg-emerald-100 dark:bg-emerald-900/40', border: 'border-emerald-300 dark:border-emerald-700', dot: 'bg-emerald-500' },
    { bg: 'bg-amber-100 dark:bg-amber-900/40', border: 'border-amber-300 dark:border-amber-700', dot: 'bg-amber-500' },
    { bg: 'bg-rose-100 dark:bg-rose-900/40', border: 'border-rose-300 dark:border-rose-700', dot: 'bg-rose-500' },
    { bg: 'bg-violet-100 dark:bg-violet-900/40', border: 'border-violet-300 dark:border-violet-700', dot: 'bg-violet-500' },
    { bg: 'bg-cyan-100 dark:bg-cyan-900/40', border: 'border-cyan-300 dark:border-cyan-700', dot: 'bg-cyan-500' },
    { bg: 'bg-lime-100 dark:bg-lime-900/40', border: 'border-lime-300 dark:border-lime-700', dot: 'bg-lime-500' },
    { bg: 'bg-fuchsia-100 dark:bg-fuchsia-900/40', border: 'border-fuchsia-300 dark:border-fuchsia-700', dot: 'bg-fuchsia-500' },
    { bg: 'bg-orange-100 dark:bg-orange-900/40', border: 'border-orange-300 dark:border-orange-700', dot: 'bg-orange-500' },
    { bg: 'bg-teal-100 dark:bg-teal-900/40', border: 'border-teal-300 dark:border-teal-700', dot: 'bg-teal-500' },
    { bg: 'bg-indigo-100 dark:bg-indigo-900/40', border: 'border-indigo-300 dark:border-indigo-700', dot: 'bg-indigo-500' },
    { bg: 'bg-pink-100 dark:bg-pink-900/40', border: 'border-pink-300 dark:border-pink-700', dot: 'bg-pink-500' },
];

/** Pilih palette deterministic berdasar subject_id agar konsisten antar render. */
export function colorForSubject(subjectId: number): SubjectColor {
    const idx = ((subjectId % PALETTE.length) + PALETTE.length) % PALETTE.length;
    return PALETTE[idx];
}
