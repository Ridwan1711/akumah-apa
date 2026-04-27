# Manhood CRUD Rollout Phase

Template rollout adopsi UI/UX CRUD ke modul lain setelah pilot `admin/students`.

## Modul Prioritas
1. `admin/users`
2. `admin/diniyah-classes`
3. `admin/payments`
4. `admin/violations`
5. modul CRUD lain secara bertahap

## Checklist Per Modul
1. **Header + stats**
   - Terapkan `CrudPageHeader` dan `CrudStatStrip`.
2. **Toolbar**
   - Search + filter + aksi utama (`Tambah`, `Export`, dsb).
3. **Table shell**
   - Pakai `CrudTableShell`, konsisten badge/status/action icon.
4. **Modal**
   - Create/edit modal gunakan `CrudModal` + section form.
5. **Delete confirm**
   - Minimal confirm modal destructive action.
6. **Pagination**
   - Gunakan `CrudPagination`.
7. **Bulk action**
   - Aktifkan jika modul mendukung select multi-row.
8. **QA**
   - Cek light/dark, responsif, dan regresi interaksi.

## Definition of Done
- Visual konsisten dengan contract `docs/manhood-crud-ui-contract.md`.
- Semua filter/search/action lama tetap bekerja.
- Tidak ada linter error baru.
- Console browser bersih saat aksi CRUD utama dijalankan.

