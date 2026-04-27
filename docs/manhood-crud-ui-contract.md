# Manhood CRUD UI Contract

Dokumen ini jadi kontrak visual + interaksi untuk halaman CRUD yang memakai shell `mhs-*`.

## Scope
- Berlaku untuk halaman list CRUD (table/index), modal form create/edit, dan bulk action.
- Tidak mengganti logika backend. Fokus di lapisan UI/UX.

## Tokens
- Gunakan token dari `mhs-*` (`--mhs-primary`, `--mhs-border`, dst) agar konsisten light/dark.
- Komponen CRUD memakai prefix class `mcr-*` untuk menghindari bentrok utility Tailwind.

## Struktur Halaman CRUD
1. `mcr-page-head` — judul + deskripsi.
2. `mcr-stat-strip` — 4 kartu ringkasan.
3. `mcr-toolbar` — search, filter, action buttons.
4. `mcr-table-shell` + `mcr-table` — data utama.
5. `mcr-pagination` — navigasi halaman.
6. `mcr-bulk-bar` — aksi massal jika ada row terseleksi.
7. `mcr-card` (opsional) — panel tambahan seperti riwayat import.

## Kontrak Interaksi
- Search: debounce 300ms, server-side filter.
- Filter: change -> request Inertia preserve state + scroll.
- Row actions: lihat, edit, hapus.
- Delete: wajib confirm modal.
- Bulk: tampil hanya saat selected rows > 0.
- Modal:
  - header: title + subtitle
  - body: grouped form section
  - footer: CTA kanan bawah (`Batal`, `Simpan/Proses`)

## Responsif
- Desktop: toolbar horizontal, stat strip 4 kolom.
- Tablet: stat strip 2 kolom.
- Mobile: stat strip 1 kolom, toolbar stack vertical, form grid 1 kolom.

## Accessibility Minimum
- Tombol/icon action harus punya `title` atau `aria-label`.
- Modal close via tombol close + aksi batal.
- Semua input form punya `label`.

