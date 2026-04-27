# Permission Rollout Checklist

## Persona Matrix
- super_admin: harus lolos semua endpoint.
- admin_keuangan_full: akses invoice/payment/report penuh.
- musyrif_no_pengurus: tidak melihat invoice santri pengurus aktif.
- musyrif_division_limited: hanya melihat invoice pengurus pada `division_code` yang di-scope.
- santri_pengurus_aktif: tetap akses portal santri, namun invoice visibility mengikuti policy caller.
- wali_santri: tetap akses portal wali tanpa akses admin keuangan.

## Endpoint Verification
- `/api/v1/admin/invoices`
- `/api/v1/admin/invoices/{invoice}`
- `/api/v1/admin/payments`
- `/api/v1/admin/payment-reports/summary`
- `/api/v1/admin/payment-reports/arrears`

## Backward Compatibility
- Middleware `role` tetap aktif selama transisi.
- Middleware `permission` ditambahkan pada area kritikal keuangan.
- Sidebar web memanfaatkan permission dengan fallback role lama.
- Flutter redirect memakai permission guard, tetap fallback role.

## Cutover Notes
- Setelah seluruh role sudah punya mapping permission stabil, kurangi penggunaan `role:*` pada route bertahap.
- Pertahankan logging penolakan permission untuk audit selama masa transisi.

