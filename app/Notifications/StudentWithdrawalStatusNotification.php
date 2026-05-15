<?php

namespace App\Notifications;

use App\Models\Role;
use App\Models\StudentWithdrawalRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentWithdrawalStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StudentWithdrawalRequest $request,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $body = $this->body();

        return [
            'type' => 'student_withdrawal_status',
            'title' => $this->title(),
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'entity_type' => 'student_withdrawal_request',
            'entity_id' => (string) $this->request->id,
            'role_target' => $roleTarget,
            'priority' => 'p1',
            'collapse_key' => 'student_withdrawal_'.$this->request->id.'_'.$this->event,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $title = $this->title();
        $body = $this->body();

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'student_withdrawal_status',
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'entity_type' => 'student_withdrawal_request',
                'entity_id' => (string) $this->request->id,
                'role_target' => $roleTarget,
                'priority' => 'p1',
                'collapse_key' => 'student_withdrawal_'.$this->request->id.'_'.$this->event,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'santri_pending' => 'Konfirmasi Keluar Pesantren',
            'wali_pending' => 'Konfirmasi Wali Diperlukan',
            'pending_admin' => 'Menunggu Admin',
            'admin_pending' => 'Permohonan Keluar Santri',
            'approved' => 'Keluar Pesantren Disetujui',
            'rejected' => 'Permohonan Keluar Ditolak',
            'closed_continue' => 'Tetap di Pesantren',
            'cancelled' => 'Permohonan Dibatalkan',
            default => 'Keputusan Keluar Pesantren',
        };
    }

    private function body(): string
    {
        $name = $this->request->student?->full_name ?? 'Santri';

        return match ($this->event) {
            'santri_pending' => "Wali sudah mengisi keputusan untuk {$name}. Silakan konfirmasi pilihan Anda.",
            'wali_pending' => "Santri {$name} sudah mengisi keputusan. Silakan konfirmasi sebagai wali (keputusan wali yang berlaku).",
            'pending_admin', 'admin_pending' => "Permohonan keluar pesantren {$name} menunggu persetujuan admin.",
            'approved' => "Permohonan keluar pesantren {$name} telah disetujui admin.",
            'rejected' => "Permohonan keluar pesantren {$name} ditolak admin.",
            'closed_continue' => "Berdasarkan konfirmasi bersama, {$name} tetap melanjutkan di pesantren.",
            'cancelled' => "Permohonan keluar pesantren untuk {$name} dibatalkan.",
            default => "Status permohonan keluar pesantren {$name} diperbarui.",
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole(Role::WALI_SANTRI)) {
            return ['/wali/children/'.$this->request->student_id.'/withdrawal', 'wali'];
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole(Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK)) {
            return ['/admin/student-withdrawals', 'admin'];
        }

        return ['/santri/withdrawal', 'santri'];
    }
}
