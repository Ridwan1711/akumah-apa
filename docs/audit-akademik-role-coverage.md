# Audit Kelengkapan Akademik per Role

Tanggal audit: 2026-04-23

## Ringkasan Cepat

- Fitur akademik untuk `admin_akademik` dan `super_admin` sudah paling lengkap.
- Fitur role non-admin (guru, santri, wali, wali-kelas) belum konsisten antara Web dan API.
- Khusus area jadwal, ada gap UX: jadwal tersedia di API untuk guru/santri, tetapi belum terlihat jelas di portal Web/sidebar.

## Cakupan Role dan Modul Akademik

### 1) `super_admin` / `admin_akademik`

Sudah tersedia (Web):

- Master data akademik: tahun ajaran, kelas diniyah, mapel, komponen penilaian, rule penilaian.
- Penugasan guru.
- Jadwal legacy (`/admin/schedules`) + jadwal matrix (`/admin/schedule-sets`).
- Kehadiran santri.
- Nilai kitab.
- Tahfidz.
- Raport + template raport.
- Kenaikan kelas.

Kesimpulan: cukup lengkap.

### 2) `guru`

Yang tersedia:

- Web: menu `Nilai Kitab` (via `can.access.kitab-grades`).
- API: dashboard guru, teaching assignments, jadwal (`/api/v1/guru/schedule`), sesi/kehadiran, raport kelas.

Gap:

- Portal Web tidak menyediakan halaman jadwal guru yang eksplisit, padahal API sudah ada.
- Menu guru di sidebar web masih minimal (hanya nilai kitab), sehingga cakupan akademik guru di Web belum setara API.

### 3) `santri`

Yang tersedia:

- Web: nilai, tahfidz, pelanggaran, profil.
- API: nilai, tahfidz, pelanggaran, profil, izin, jadwal (`/api/v1/santri/schedule`), kehadiran.

Gap:

- Portal Web belum memiliki halaman jadwal santri.
- Portal Web belum memiliki halaman riwayat kehadiran yang setara endpoint API.

### 4) `wali_santri`

Yang tersedia:

- Web: data anak, detail anak (nilai, ringkas tahfidz/pelanggaran), tagihan, riwayat pembayaran.
- API: data anak, detail anak, pembayaran.

Gap:

- Belum ada halaman jadwal anak untuk wali di Web maupun API.
- Monitoring kehadiran anak belum terlihat sebagai modul tersendiri di portal wali.

### 5) `wali-kelas` (berbasis record homeroom)

Yang tersedia:

- Web: raport kelas (index/preview/save notes/pdf).
- API: fungsi raport kelas tersedia di grup guru.

Gap:

- Tidak ada halaman jadwal wali-kelas/homeroom secara khusus di portal web.
- Tidak ada menu akademik lain (mis. kehadiran kelas) di area wali-kelas, walau kebutuhan operasional biasanya ada.

### 6) `musyrif`

Yang tersedia:

- Web: tahfidz, pelanggaran, perizinan.

Catatan:

- Ini lebih operasional-asrama daripada akademik formal, dan saat ini sudah sesuai scope yang dipakai.

## Fokus Jadwal: Apakah Semua Role Sudah Kebagian?

### Sudah kebagian

- Admin akademik/super admin: penuh (legacy + matrix editor).
- Guru: ada via API.
- Santri: ada via API.

### Belum kebagian secara portal Web

- Guru: belum ada halaman jadwal di web sidebar.
- Santri: belum ada halaman jadwal di web sidebar.
- Wali santri: belum ada akses jadwal anak.
- Wali-kelas: belum ada jadwal kelas/homeroom khusus.

## Temuan Ketidaklengkapan Prioritas

1. Konsistensi Web vs API belum seimbang untuk role non-admin (terutama jadwal dan kehadiran).
2. Akses jadwal untuk wali/wali-kelas belum ada, padahal ini sering jadi kebutuhan komunikasi harian.
3. Menu role-based di sidebar web masih belum memanfaatkan endpoint akademik yang sudah tersedia di API.

## Rekomendasi Implementasi Bertahap

### Prioritas 1 (cepat, dampak besar)

- Tambah halaman `Jadwal Guru` di Web (gunakan data dari endpoint yang setara logika API guru).
- Tambah halaman `Jadwal Santri` di Web.
- Tambah menu sidebar untuk dua halaman tersebut.

### Prioritas 2

- Tambah halaman `Kehadiran Santri` untuk portal santri (read-only) agar parity dengan API.
- Tambah halaman `Jadwal Anak` untuk wali santri.

### Prioritas 3

- Evaluasi kebutuhan `Jadwal Wali-Kelas` + `monitor kehadiran kelas` untuk wali-kelas.
- Rapikan boundary: fitur yang sudah ada di API tapi belum ada di Web ditracking sebagai backlog parity.

## Backlog Parity Wali-Kelas (Hasil Evaluasi)

Status saat ini:

- Wali-kelas sudah punya akses raport kelas via portal web (`/wali-kelas/report-cards`).
- Belum ada route/API khusus untuk jadwal wali-kelas atau monitoring kehadiran kelas dari perspektif homeroom.

Keputusan backlog fase berikutnya:

1. Tambah endpoint API `wali-kelas` untuk:
   - jadwal kelas binaan (read-only),
   - rekap kehadiran kelas binaan (read-only, per rentang tanggal/status).
2. Tambah portal web wali-kelas:
   - halaman `Jadwal Kelas Binaan`,
   - halaman `Kehadiran Kelas`.
3. Pertahankan boundary: wali-kelas hanya bisa melihat data kelas yang diampu melalui relasi homeroom, bukan seluruh kelas.
4. Validasi kebutuhan operasional lanjutan (mis. tindakan koreksi) diputuskan setelah penggunaan read-only stabil.

## Status Umum

- Core akademik admin: baik.
- Role coverage lintas portal: menengah (belum merata).
- Gap terbesar saat ini: experience jadwal di portal non-admin.
