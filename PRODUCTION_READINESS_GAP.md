# Production Readiness Gap - siakad-manhood

Tanggal audit: 2026-04-25
Status: Belum production-ready penuh (perlu hardening sebelum go-live).

## Ringkasan

Fondasi aplikasi sudah cukup baik (Laravel modern, health route `/up`, test dan lint dasar tersedia), tetapi masih ada gap penting pada konfigurasi environment, dependency pinning, keamanan token/session, observability, dan quality gate CI.

## Prioritas Tinggi (High)

1. Konfigurasi contoh environment masih mode dev:
   - `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`
   - Bukti: `.env.example`
   - Dampak: risiko kebocoran detail error/log berlebih jika disalin ke server produksi.

2. Beberapa dependency Composer pakai wildcard `*`:
   - `barryvdh/laravel-dompdf`, `endroid/qr-code`, `kreait/laravel-firebase`, `maatwebsite/excel`, `midtrans/midtrans-php`
   - Bukti: `composer.json`
   - Dampak: build tidak deterministik, risiko update mayor tidak sengaja.

3. Token Sanctum tidak punya masa berlaku default:
   - `'expiration' => null`
   - Bukti: `config/sanctum.php`
   - Dampak: token dapat valid terlalu lama jika tidak ada kebijakan revoke kuat.

4. Exception reporting belum diintegrasikan:
   - `withExceptions` masih kosong
   - Bukti: `bootstrap/app.php`
   - Dampak: monitoring insiden produksi lemah (sulit deteksi dan RCA cepat).

5. Callback payment dikecualikan CSRF (wajar), tetapi harus dipastikan validasi webhook ketat:
   - `payment/midtrans/notification` masuk daftar CSRF exception
   - Bukti: `bootstrap/app.php`
   - Dampak: endpoint callback bisa jadi target abuse jika verifikasi signature/payload tidak ketat.

## Prioritas Sedang (Medium)

1. Workflow test belum menjadi quality gate lengkap:
   - CI tests hanya menjalankan `pest`
   - Bukti: `.github/workflows/tests.yml`
   - Catatan: sudah ada `composer ci:check`, tetapi belum dipakai di workflow.

2. Workflow lint masih memakai mode perbaikan otomatis (`--fix`) pada frontend:
   - `npm run lint` memanggil `eslint . --fix`
   - Bukti: `.github/workflows/lint.yml`, `package.json`
   - Dampak: hasil CI bisa tidak deterministik untuk mode "check only".

3. Session encryption default nonaktif:
   - `SESSION_ENCRYPT=false`
   - Bukti: `.env.example`
   - Dampak: data session at-rest tidak terenkripsi (tergantung threat model).

4. Domain stateful Sanctum masih contoh lokal:
   - `SANCTUM_STATEFUL_DOMAINS` masih localhost
   - Bukti: `.env.example`, `config/sanctum.php`
   - Dampak: auth cookie SPA bisa gagal / salah konfigurasi di produksi.

## Prioritas Rendah (Low)

1. `laravel/tinker` ada di dependency runtime:
   - Bukti: `composer.json`
   - Catatan: aman jika deploy dengan `composer install --no-dev` dan akses shell terkontrol.

2. Operasional queue perlu disiplin worker:
   - `.env.example` menyiratkan queue database
   - Bukti: `.env.example`
   - Catatan: tanpa worker, job import/background akan menumpuk.

## Rekomendasi Implementasi Lanjutan

1. Hardening env produksi:
   - Set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info/error`, `APP_URL` domain asli.
2. Pin versi dependency:
   - Ganti wildcard `*` ke versi terkontrol, lalu audit rutin (`composer audit`).
3. Kebijakan token:
   - Set `SANCTUM` expiration atau mekanisme revoke yang jelas.
4. Observability:
   - Integrasi Sentry/Crash reporting + log aggregation.
5. CI gate:
   - Gunakan `composer ci:check` (lint/check/types/tests) sebagai syarat merge.
6. Security payment callback:
   - Verifikasi signature/payload/idempotency webhook secara eksplisit dan terdokumentasi.

## Keputusan Saat Ini

Belum bisa disebut "production-only" secara aman. Bisa lanjut ke produksi setelah poin High selesai, lalu Medium ditargetkan sebagai hardening batch berikutnya.
