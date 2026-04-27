# Push Notification Plan - Laravel

Dokumen ini menjadi panduan implementasi backend notifikasi push yang dikirim ke Flutter melalui Firebase Cloud Messaging (FCM), dengan fallback ke in-app notification (`database notifications`).

## 1) Tujuan Backend

- Menstandarkan event notifikasi lintas role (Santri, Guru, Wali, Admin).
- Menjamin event penting terkirim via FCM + tersimpan di inbox aplikasi.
- Menjaga payload konsisten agar Flutter bisa routing/deeplink dengan aman.
- Menyediakan observability dan mekanisme cleanup token invalid.

## 2) Event Matrix Per Role dan Prioritas

## P0 (wajib)

| Event Type | Trigger | Target Role | Channel | Deep Link |
|---|---|---|---|---|
| `schedule_tomorrow` | Scheduler harian | Santri, Guru, Wali | FCM + database | `/santri/schedule`, `/guru/schedule`, `/wali/children/{id}/schedule` |
| `schedule_changed` | Admin ubah jadwal efektif | Santri, Guru, Wali | FCM + database | halaman jadwal sesuai role |
| `invoice_created` | Tagihan baru terbit | Wali | FCM + database | `/wali/invoices/{id}` |
| `invoice_due_soon` | H-3/H-1 jatuh tempo | Wali | FCM + database | `/wali/invoices/{id}` |
| `payment_pending` | Bukti transfer diupload | Admin Keuangan | FCM + database | `/admin/payments` |
| `payment_verified` | Pembayaran diverifikasi | Wali | FCM + database | `/wali/invoices/{id}` |
| `student_absent` | Absensi status alpa | Wali (+opsional admin/guru) | FCM + database | `/wali/children/{id}` |
| `bulk_run_finished` | Proses bulk selesai/gagal | Operator (Admin/Guru) | FCM + database | layar modul sumber |

## P1

| Event Type | Trigger | Target Role | Channel | Deep Link |
|---|---|---|---|---|
| `report_card_published` | Rapor dipublish | Santri, Wali | FCM + database | halaman rapor |
| `grade_updated` | Nilai mapel diperbarui | Santri, Wali | FCM + database | halaman nilai |
| `violation_recorded` | Pelanggaran dicatat | Wali, Admin terkait | FCM + database | detail pelanggaran |
| `leave_status_changed` | Izin disetujui/ditolak | Santri, Wali, Admin | FCM + database | daftar izin |
| `announcement_segmented` | Pengumuman tersegmentasi | Role target | FCM + database | halaman pengumuman |

## P2

| Event Type | Trigger | Target Role | Channel | Deep Link |
|---|---|---|---|---|
| `profile_incomplete_reminder` | Data profil belum lengkap | Semua role terkait | FCM + database | `/profile/edit` |
| `periodic_reminder` | Reminder non-kritis | Role target | FCM + database | sesuai konteks |

## 3) Payload Contract Standar (FCM + Inbox)

Gunakan contract tunggal untuk semua notification class.

## Wajib

- `type`: string, snake_case (`invoice_created`, `student_absent`)
- `title`: string pendek
- `body`: string ringkas
- `url`: relative app route, diawali `/`
- `entity_type`: contoh `invoice`, `student`, `schedule`
- `entity_id`: string (cast dari int)
- `role_target`: `santri|guru|wali|admin|multi`
- `priority`: `p0|p1|p2`
- `sent_at`: ISO8601 UTC
- `notification_id`: uuid/ulid

## Opsional

- `context`: JSON-encoded string (contoh metadata tambahan)
- `collapse_key`: grouping key untuk deduplikasi device
- `action`: intent semantic (`open_invoice`, `open_schedule`)

## Contoh payload data

```json
{
  "type": "invoice_created",
  "title": "Tagihan Baru",
  "body": "Tagihan bulan Mei telah diterbitkan.",
  "url": "/wali/invoices/456",
  "entity_type": "invoice",
  "entity_id": "456",
  "role_target": "wali",
  "priority": "p0",
  "sent_at": "2026-04-26T02:30:00Z",
  "notification_id": "01HTXYZ..."
}
```

## 4) Arsitektur dan Alur Backend

```mermaid
flowchart TD
  eventSource[DomainEventOrCommand] --> notificationClass[LaravelNotificationClass]
  notificationClass --> channels{via()}
  channels --> dbChannel[database]
  channels --> fcmChannel[FcmChannel]
  fcmChannel --> tokenResolver[User.routeNotificationForFcm]
  tokenResolver --> firebaseMessaging[FirebaseMessagingMulticast]
  firebaseMessaging --> cleanup[InvalidTokenCleanup]
  dbChannel --> inboxApi[NotificationControllerAPI]
  inboxApi --> flutterClient[FlutterNotificationsPage]
```

## 5) Implementasi Teknis Laravel

## A. Standarisasi notification class

- Semua class notifikasi domain penting wajib:
  - `implements ShouldQueue`
  - `via()` berisi `['database', FcmChannel::class]`
  - punya `toDatabase()` dan `toFcm()` dengan contract yang sama.

Target file awal:
- `app/Notifications/PaymentPendingNotification.php`
- `app/Notifications/PaymentVerifiedNotification.php`
- `app/Notifications/BulkRunFinishedNotification.php`
- `app/Notifications/InvoiceOverdueNotification.php`

## B. Producer event

- Pastikan producer memanggil notifikasi pada titik bisnis:
  - invoice baru
  - due soon / overdue checker
  - verifikasi pembayaran
  - update jadwal
  - rapor publish
  - pelanggaran/izin

## C. Routing target role

- Satu helper/service untuk normalisasi penerima:
  - `NotificationAudienceResolver`
- Hindari hard-coded role string tersebar.

## D. Token hygiene

- Pertahankan cleanup invalid token di `FcmChannel`.
- Tambah scheduled prune token lama (misalnya `last_used_at > 90 hari`).

## E. Inbox API enhancement

Perluasan `NotificationController`:
- tambah pagination (`page`, `per_page`)
- tambah mode `all` atau `unread_only`
- sertakan `read_at` agar Flutter bisa unread akurat
- tambah endpoint `DELETE` (opsional) untuk dismiss history

## 6) Aturan Kualitas dan Reliabilitas

- Semua payload data FCM harus string-safe.
- `url` wajib path internal whitelist.
- Retry queue terkonfigurasi (backoff + max tries).
- Deduplikasi berbasis `notification_id`/`collapse_key` untuk event repetitive.

## 7) Observability

## Logging minimum

- Log kirim notifikasi: `type`, `target_user_id`, `channel`, `status`, `notification_id`.
- Log error FCM: response code, token count invalid.

## Metrics minimum

- `notif_sent_total{type,channel}`
- `notif_failed_total{type,reason}`
- `notif_invalid_token_total`
- `notif_queue_latency_ms`

## 8) Test Plan Laravel

## Unit test

- `toFcm()` payload contract mandatory keys.
- `via()` untuk event P0 harus termasuk `FcmChannel`.
- resolver audience per role.

## Integration test

- API register/unregister token.
- endpoint inbox (`index`, `read`, `read-all`) dengan data nyata.
- trigger business action -> notif tersimpan di database.

## Manual verification

- Simulasikan event P0, cek:
  - row di `notifications`
  - payload FCM valid
  - invalid token terhapus otomatis

## 9) Rollout Bertahap

1. **Phase 1**: standardisasi payload + P0 finance/attendance/schedule.
2. **Phase 2**: API inbox enhancement + pagination + unread marker.
3. **Phase 3**: P1 academic & discipline events.
4. **Phase 4**: P2 reminders + optimization + metrics dashboard.

## 10) Definition of Done (Backend)

- Semua event P0 kirim FCM + database.
- Contract payload tunggal dipakai semua notifikasi.
- Inbox API mendukung unread akurat.
- Test suite P0 lulus.
- Terdapat log dan metrik minimum di production.
