# Permission Taxonomy & Scope

## Core Principle
- Role dipakai sebagai bundel default permission.
- Permission menentukan aksi fitur.
- Scope menentukan subset data yang boleh diakses user.

## Permission Naming
- Format: `domain.action` atau `domain.action.scope`.
- Contoh:
  - `invoice.view`
  - `invoice.view_all`
  - `invoice.view_non_pengurus`
  - `invoice.view_pengurus_division`
  - `payment.view`
  - `payment.report.view`

## Scope Keys
- `division_code`: membatasi data pengurus berdasarkan divisi.
- `asrama_id`: membatasi data berdasarkan asrama.

## Finance Visibility Rules
1. Jika punya `invoice.view_all`: lihat semua tagihan.
2. Jika punya `invoice.view_pengurus_division`: lihat non-pengurus + pengurus yang division-nya masuk scope.
3. Jika punya `invoice.view_non_pengurus`: hanya non-pengurus aktif.
4. Selain itu: tidak ada akses data tagihan.

## Initial Role Mapping
- `super_admin`: wildcard semua permission.
- `admin_keuangan`: invoice/payment/report set (tanpa otomatis `view_all`).
- `musyrif`: `invoice.view` + `invoice.view_non_pengurus`.
- role lain: dashboard/fitur minimal sesuai fungsi.

